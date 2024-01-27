<?php

declare(strict_types = 1);

use Consolidation\AnnotatedCommand\CommandResult;
use League\Container\Container as LeagueContainer;
use NuvoleWeb\Robo\Task\Config\Robo\loadTasks as ConfigLoader;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Collection\CallableTask;
use Robo\Collection\CollectionBuilder;
use Robo\Common\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\TaskInterface;
use Robo\State\Data as RoboState;
use Robo\Tasks;
use Sweetchuck\ComposerLockDiff\LockDiffer;
use Sweetchuck\ComposerLockDiff\Reporter\MarkdownTableReporter;
use Sweetchuck\LintReport\Reporter\BaseReporter;
use Sweetchuck\Robo\ComposerLockDiff\ComposerLockDiffTaskLoader;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Sweetchuck\Robo\Phpcs\PhpcsTaskLoader;
use Sweetchuck\Robo\Phpstan\PhpstanTaskLoader;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class RoboFile extends Tasks implements LoggerAwareInterface, ConfigAwareInterface
{
    use LoggerAwareTrait;
    use ConfigAwareTrait;
    use ConfigLoader;
    use GitTaskLoader;
    use PhpcsTaskLoader;
    use PhpstanTaskLoader;
    use ComposerLockDiffTaskLoader;

    /**
     * @var array<string, mixed>
     */
    protected array $composerInfo = [];

    /**
     * @var array<string, mixed>
     */
    protected array $codeceptionInfo = [];

    protected string $shell = '/bin/bash';

    /**
     * @var string[]
     */
    protected array $codeceptionSuiteNames = [];

    protected string $packageVendor = '';

    protected string $packageName = '';

    protected string $binDir = 'vendor/bin';

    protected string $gitHook = '';

    protected string $envVarNamePrefix = '';

    protected string $environmentType = '';

    /**
     * Allowed values: local, jenkins, travis.
     */
    protected string $environmentName = '';

    /**
     * RoboFile constructor.
     */
    public function __construct()
    {
        $this
            ->initShell()
            ->initComposerInfo()
            ->initEnvVarNamePrefix()
            ->initEnvironmentTypeAndName();
    }

    /**
     * @hook pre-command @initLintReporters
     */
    public function onHookPreCommandInitLintReporters(): void
    {
        $lintServices = BaseReporter::getServices();
        $container = $this->getContainer();
        if (!($container instanceof LeagueContainer)) {
            return;
        }

        foreach ($lintServices as $name => $class) {
            if ($container->has($name)) {
                continue;
            }

            $container->add($name, $class);
            $container->extend($name)->setShared(false);
        }
    }

    /**
     * Git "pre-commit" hook callback.
     *
     * @command githook:pre-commit
     *
     * @hidden
     *
     * @initLintReporters
     */
    public function githookPreCommit(): CollectionBuilder
    {
        $this->gitHook = 'pre-commit';

        return $this
            ->collectionBuilder()
            ->addTaskList(array_filter([
                'composer.validate' => $this->getTaskComposerValidate(),
                'circleci.config.validate' => $this->getTaskCircleCiConfigValidate(),
                'phpcs.lint' => $this->getTaskPhpcsLint(),
                'phpstan.analyze' => $this->getTaskPhpstanAnalyze(),
                'codecept.run' => $this->getTaskCodeceptRunSuites(),
            ]));
    }

    /**
     * Git "prepare-commit-msg" hook callback.
     *
     * @param string $msgFilePath
     *   The name of the file that contains the commit log message.
     * @param string $msgSource
     *   The source of the commit message.
     *   - message (-m, --message, -F)
     *   - template (-t or commit.template)
     *   - merge
     *   - squash
     * @param string $commitObjectName
     *   Commit object name.
     *
     * @command githook:prepare-commit-msg
     *
     * @hidden
     */
    public function cmdGithookPrepareCommitMsgExecute(
        string $msgFilePath,
        string $msgSource = '',
        string $commitObjectName = '',
    ): ?CollectionBuilder {
        if ($msgSource === 'merge' || $msgSource === 'squash') {
            return null;
        }

        $this->gitHook = 'prepare-commit-msg';

        $cb = $this->collectionBuilder();

        $composerJson = preg_replace(
            '@^\./@',
            '',
            getenv('COMPOSER') ?: './composer.json',
        );
        $composerLock = preg_replace(
            '@\.json$@',
            '.lock',
            $composerJson,
        );

        $paths = array_unique([
            'composer.json',
            'composer.lock',
            $composerJson,
            $composerLock,
            'composer.*.json',
            'composer.*.lock',
        ]);

        $cb->addTask(
            $this
                ->taskGitListStagedFiles()
                ->setAssetNamePrefix('app.cld.')
                ->setPaths($paths)
                ->setDiffFilter(['d' => false])
        );
        $cb->addTask($this->getTaskGitHookPrepareCommitMsgMakeQuartets($cb, 'app.cld.files'));

        $cb->addCode(function (RoboState $state) use ($msgFilePath): int {
            $state['app.cld.msgFilePath.handler'] = fopen($msgFilePath, 'a');
            $state['app.cld.reporter'] = new MarkdownTableReporter();
            $state['app.cld.reporter']->setStream($state['app.cld.msgFilePath.handler']);

            return 0;
        });

        $taskForEach = $this->taskForEach();
        $taskForEach
            ->iterationMessage('Append ComposerLockDiff table for {key}')
            ->deferTaskConfiguration('setIterable', 'app.cld.entries_list')
            ->withBuilder(function (
                CollectionBuilder $builder,
                string $key,
                $entries,
            ) use ($taskForEach): void {
                $state = $taskForEach->getState();

                $builder
                    ->addcode(function () use ($key, $state): int {
                        fwrite(
                            $state['app.cld.msgFilePath.handler'],
                            "\nChanges in $key\n",
                        );

                        return 0;
                    })
                    ->addTask(
                        $this
                            ->taskComposerLockDiffReport()
                            ->setEntries($entries)
                            ->setReporter($state['app.cld.reporter'])
                    );
            });
        $cb->addTask($taskForEach);

        $cb->addCode(function (RoboState $state): int {
            fclose($state['app.cld.msgFilePath.handler']);

            return 0;
        });

        return $cb;
    }

    protected function getTaskGitHookPrepareCommitMsgMakeQuartets(
        CollectionBuilder $reference,
        string $filesStateKey = 'files',
    ): TaskInterface {
        return new CallableTask(
            function (RoboState $state) use ($filesStateKey): int {
                $lockDiffer = new LockDiffer();
                $state['app.cld.entries_list'] = [];
                /** @var Sweetchuck\Robo\Git\ListStagedFilesItem $item */
                foreach ($state[$filesStateKey] ?? [] as $item) {
                    if (!str_ends_with($item->fileName, '.lock')) {
                        continue;
                    }

                    $id = mb_substr($item->fileName, 0, mb_strlen('.lock') * -1);
                    $entries = $lockDiffer->diff(
                        $this->safeJsonDecode($this->gitReadFileContent('HEAD', "$id.lock")),
                        $this->safeJsonDecode($this->gitReadFileContent('', "$id.lock")),
                        // @phpstan-ignore-next-line
                        $this->safeJsonDecode($this->gitReadFileContent('HEAD', "$id.json")),
                        // @phpstan-ignore-next-line
                        $this->safeJsonDecode($this->gitReadFileContent('', "$id.json")),
                    );
                    if ($entries) {
                        $state['app.cld.entries_list'][$item->fileName] = $entries;
                    }
                }

                return 0;
            },
            $reference,
        );
    }

    /**
     * @return null|array<string, mixed>
     */
    protected function safeJsonDecode(?string $string): ?array
    {
        return $string === null ?
            null
            : json_decode($string, true);
    }

    protected function gitReadFileContent(string $ref, string $filePath): ?string
    {
        /** @var \Robo\Application $app */
        $app = $this
            ->getContainer()
            ->get('application');

        /** @var \Symfony\Component\Console\Helper\ProcessHelper $processHelper */
        $processHelper = $app
            ->getHelperSet()
            ->get('process');

        $command = [
            'git',
            'show',
            "$ref:$filePath",
        ];
        $process = $processHelper->run($this->output(), $command);

        return $process->getExitCode() === 0 ?
            $process->getOutput()
            : null;
    }

    /**
     * @option string $format
     *   Default: yaml
     *
     * @command config:export
     */
    public function cmdConfigExportExecute(): CommandResult
    {
        return CommandResult::data($this->getConfig()->export());
    }

    /**
     * Run the Robo unit tests.
     *
     * @param array<string> $suiteNames
     *
     * @command test
     */
    public function test(array $suiteNames): CollectionBuilder
    {
        $this->validateArgCodeceptionSuiteNames($suiteNames);

        return $this->getTaskCodeceptRunSuites($suiteNames);
    }

    /**
     * Run code style checkers.
     *
     * @command lint
     *
     * @initLintReporters
     */
    public function lint(): CollectionBuilder
    {
        return $this
            ->collectionBuilder()
            ->addTaskList(array_filter([
                'composer.validate' => $this->getTaskComposerValidate(),
                'circleci.config.validate' => $this->getTaskCircleCiConfigValidate(),
                'phpcs.lint' => $this->getTaskPhpcsLint(),
                'phpstan.analyze' => $this->getTaskPhpstanAnalyze(),
            ]));
    }

    /**
     * @command lint:phpcs
     *
     * @initLintReporters
     */
    public function cmdLintPhpcsExecute(): TaskInterface
    {
        return $this->getTaskPhpcsLint();
    }

    /**
     * @command lint:phpstan
     *
     * @initLintReporters
     */
    public function cmdLintPhpstanExecute(): TaskInterface
    {
        return $this->getTaskPhpstanAnalyze();
    }

    protected function getTaskPhpstanAnalyze(): TaskInterface
    {
        /** @var \Sweetchuck\LintReport\Reporter\VerboseReporter $verboseReporter */
        $verboseReporter = $this->getContainer()->get('lintVerboseReporter');
        $verboseReporter->setFilePathStyle('relative');

        return $this
            ->taskPhpstanAnalyze()
            ->setNoProgress(true)
            ->setNoInteraction(true)
            ->setMemoryLimit('512M')
            ->setErrorFormat('json')
            ->addLintReporter('lintVerboseReporter', $verboseReporter);
    }

    /**
     * @command lint:circleci-config
     */
    public function cmdLintCircleciConfigExecute(): ?TaskInterface
    {
        return $this->getTaskCircleCiConfigValidate();
    }

    protected function getTaskCircleCiConfigValidate(): ?TaskInterface
    {
        if ($this->environmentType === 'ci') {
            return null;
        }

        if ($this->gitHook === 'pre-commit') {
            $cb = $this->collectionBuilder();
            $cb->addTask(
                $this
                    ->taskGitListStagedFiles()
                    ->setPaths(['./.circleci/config.yml' => true])
                    ->setDiffFilter(['d' => false])
                    ->setAssetNamePrefix('staged.')
            );

            $cb->addTask(
                $this
                    ->taskGitReadStagedFiles()
                    ->setCommandOnly(true)
                    ->setWorkingDirectory('.')
                    ->deferTaskConfiguration('setPaths', 'staged.fileNames')
            );

            $taskForEach = $this->taskForEach();
            $taskForEach
                ->iterationMessage('CircleCI config validate: {key}')
                ->deferTaskConfiguration('setIterable', 'files')
                ->withBuilder(function (
                    CollectionBuilder $builder,
                    string $key,
                    $file
                ) {
                    $builder->addTask(
                        $this->taskExec("{$file['command']} | circleci --skip-update-check config validate -"),
                    );
                });
            $cb->addTask($taskForEach);

            return $cb;
        }

        return $this->taskExec('circleci --skip-update-check config validate');
    }

    protected function errorOutput(): ?OutputInterface
    {
        $output = $this->output();

        return ($output instanceof ConsoleOutputInterface) ? $output->getErrorOutput() : $output;
    }

    protected function initEnvVarNamePrefix(): static
    {
        $this->envVarNamePrefix = strtoupper(str_replace('-', '_', $this->packageName));

        return $this;
    }

    protected function initEnvironmentTypeAndName(): static
    {
        $this->environmentType = (string) getenv($this->getEnvVarName('environment_type'));
        $this->environmentName = (string) getenv($this->getEnvVarName('environment_name'));

        if (!$this->environmentType) {
            if (getenv('CI') === 'true') {
                // CircleCI, Travis and GitLab.
                $this->environmentType = 'ci';
            } elseif (getenv('JENKINS_HOME')) {
                $this->environmentType = 'ci';
                if (!$this->environmentName) {
                    $this->environmentName = 'jenkins';
                }
            }
        }

        if (!$this->environmentName && $this->environmentType === 'ci') {
            if (getenv('GITLAB_CI') === 'true') {
                $this->environmentName = 'gitlab';
            } elseif (getenv('TRAVIS') === 'true') {
                $this->environmentName = 'travis';
            } elseif (getenv('CIRCLECI') === 'true') {
                $this->environmentName = 'circle';
            }
        }

        if (!$this->environmentType) {
            $this->environmentType = 'dev';
        }

        if (!$this->environmentName) {
            $this->environmentName = 'local';
        }

        return $this;
    }

    protected function getEnvVarName(string $name): string
    {
        return "{$this->envVarNamePrefix}_" . strtoupper($name);
    }

    protected function initShell(): static
    {
        $this->shell = getenv('SHELL') ?: '/bin/bash';

        return $this;
    }

    protected function initComposerInfo(): static
    {
        if ($this->composerInfo) {
            return $this;
        }

        $composerFile = getenv('COMPOSER') ?: 'composer.json';
        $composerContent = file_get_contents($composerFile);
        if ($composerContent === false) {
            return $this;
        }

        $this->composerInfo = json_decode($composerContent, true);
        [$this->packageVendor, $this->packageName] = explode('/', $this->composerInfo['name']);

        if (!empty($this->composerInfo['config']['bin-dir'])) {
            $this->binDir = $this->composerInfo['config']['bin-dir'];
        }

        return $this;
    }

    /**
     * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Composer\Validate
     */
    protected function getTaskComposerValidate()
    {
        $composerExecutable = $this->getConfig()->get('composerExecutable');

        return $this->taskComposerValidate($composerExecutable);
    }

    protected function initCodeceptionInfo(): static
    {
        if ($this->codeceptionInfo) {
            return $this;
        }

        $default = [
            'paths' => [
                'tests' => 'tests',
                'log' => 'tests/_log',
                'output' => 'tests/_log',
            ],
        ];
        $dist = [];
        $local = [];

        if (is_readable('codeception.dist.yml')) {
            $dist = Yaml::parse(file_get_contents('codeception.dist.yml') ?: '{}');
        }

        if (is_readable('codeception.yml')) {
            $local = Yaml::parse(file_get_contents('codeception.yml') ?: '{}');
        }

        $this->codeceptionInfo = array_replace_recursive($default, $dist, $local);

        return $this;
    }

    /**
     * @param array<string> $suiteNames
     *
     * @return \Robo\Collection\CollectionBuilder
     */
    protected function getTaskCodeceptRunSuites(array $suiteNames = []): CollectionBuilder
    {
        if (!$suiteNames) {
            $suiteNames = ['all'];
        }

        $phpExecutables = $this->getEnabledPhpExecutables();
        $cb = $this->collectionBuilder();
        foreach ($suiteNames as $suiteName) {
            foreach ($phpExecutables as $phpExecutable) {
                $cb->addTask($this->getTaskCodeceptRunSuite($suiteName, $phpExecutable));
            }
        }

        return $cb;
    }

    /**
     * @param template-robo-task-php-executable $php
     */
    protected function getTaskCodeceptRunSuite(string $suite, array $php): CollectionBuilder
    {
        $this->initCodeceptionInfo();

        $withCoverageHtml = $this->environmentType === 'dev';
        $withCoverageXml = $this->environmentType === 'ci';

        $withUnitReportHtml = $this->environmentType === 'dev';
        $withUnitReportXml = $this->environmentType === 'ci';

        $logDir = $this->getLogDir();

        $cmdPattern = '';
        $cmdArgs = [];
        foreach ($php['envVars'] ?? [] as $envName => $envValue) {
            $cmdPattern .= "{$envName}";
            if ($envValue === null) {
                $cmdPattern .= ' ';
            } else {
                $cmdPattern .= '=%s ';
                $cmdArgs[] = escapeshellarg($envValue);
            }
        }

        $cmdPattern .= '%s';
        $cmdArgs[] = $php['command'];

        $cmdPattern .= ' %s';
        $cmdArgs[] = escapeshellcmd("{$this->binDir}/codecept");

        $cmdPattern .= ' --ansi';
        $cmdPattern .= ' --verbose';
        $cmdPattern .= ' --debug';

        $cb = $this->collectionBuilder();
        if ($withCoverageHtml) {
            $cmdPattern .= ' --coverage-html=%s';
            $cmdArgs[] = escapeshellarg("human/coverage/$suite/html");

            $cb->addTask(
                $this
                    ->taskFilesystemStack()
                    ->mkdir("$logDir/human/coverage/$suite")
            );
        }

        if ($withCoverageXml) {
            $cmdPattern .= ' --coverage-xml=%s';
            $cmdArgs[] = escapeshellarg("machine/coverage/$suite/coverage.xml");
        }

        if ($withCoverageHtml || $withCoverageXml) {
            $cmdPattern .= ' --coverage=%s';
            $cmdArgs[] = escapeshellarg("machine/coverage/$suite/coverage.serialized");

            $cb->addTask(
                $this
                    ->taskFilesystemStack()
                    ->mkdir("$logDir/machine/coverage/$suite")
            );
        }

        if ($withUnitReportHtml) {
            $cmdPattern .= ' --html=%s';
            $cmdArgs[] = escapeshellarg("human/junit/junit.$suite.html");

            $cb->addTask(
                $this
                    ->taskFilesystemStack()
                    ->mkdir("$logDir/human/junit")
            );
        }

        if ($withUnitReportXml) {
            $cmdPattern .= ' --xml=%s';
            $cmdArgs[] = escapeshellarg("machine/junit/junit.$suite.xml");

            $cb->addTask(
                $this
                    ->taskFilesystemStack()
                    ->mkdir("$logDir/machine/junit")
            );
        }

        $cmdPattern .= ' run';
        if ($suite !== 'all') {
            $cmdPattern .= ' %s';
            $cmdArgs[] = escapeshellarg($suite);
        }

        $envDir = $this->codeceptionInfo['paths']['envs'];
        $envFileName = "{$this->environmentType}.{$this->environmentName}";
        if (file_exists("$envDir/$envFileName.yml")) {
            $cmdPattern .= ' --env %s';
            $cmdArgs[] = escapeshellarg($envFileName);
        }

        if ($this->environmentType === 'ci' && $this->environmentName === 'jenkins') {
            // Jenkins has to use a post-build action to mark the build "unstable".
            $cmdPattern .= ' || [[ "${?}" == "1" ]]';
        }

        $command = vsprintf($cmdPattern, $cmdArgs);

        return $cb
            ->addCode(function () use ($command, $php) {
                $this->output()->writeln(strtr(
                    '<question>[{name}]</question> runs <info>{command}</info>',
                    [
                        '{name}' => 'Codeception',
                        '{command}' => $command,
                    ]
                ));

                $process = Process::fromShellCommandline(
                    $command,
                    null,
                    $php['envVars'] ?? null,
                    null,
                    null,
                );

                return $process->run(function ($type, $data) {
                    switch ($type) {
                        case Process::OUT:
                            $this->output()->write($data);
                            break;

                        case Process::ERR:
                            $this->errorOutput()->write($data);
                            break;
                    }
                });
            });
    }

    /**
     * @return \Sweetchuck\Robo\Phpcs\Task\PhpcsLintFiles|\Robo\Collection\CollectionBuilder
     */
    protected function getTaskPhpcsLint()
    {
        $options = [
            'failOn' => 'warning',
            'lintReporters' => [
                'lintVerboseReporter' => null,
            ],
        ];

        $logDir = $this->getLogDir();
        if ($this->environmentType === 'ci' && $this->environmentName === 'jenkins') {
            $options['failOn'] = 'never';
            $options['lintReporters']['lintCheckstyleReporter'] = $this
                ->getContainer()
                ->get('lintCheckstyleReporter')
                ->setDestination("$logDir/machine/checkstyle/phpcs.psr2.xml");
        }

        if ($this->gitHook === 'pre-commit') {
            return $this
                ->collectionBuilder()
                ->addTask($this
                    ->taskPhpcsParseXml()
                    ->setAssetNamePrefix('phpcsXml.'))
                ->addTask($this
                    ->taskGitListStagedFiles()
                    ->setPaths(['*.php' => true])
                    ->setDiffFilter(['d' => false])
                    ->setAssetNamePrefix('staged.'))
                ->addTask($this
                    ->taskGitReadStagedFiles()
                    ->setCommandOnly(true)
                    ->setWorkingDirectory('.')
                    ->deferTaskConfiguration('setPaths', 'staged.fileNames'))
                ->addTask($this
                    ->taskPhpcsLintInput($options)
                    ->deferTaskConfiguration('setFiles', 'files')
                    ->deferTaskConfiguration('setIgnore', 'phpcsXml.exclude-patterns'));
        }

        return $this->taskPhpcsLintFiles($options);
    }

    protected function getLogDir(): string
    {
        $this->initCodeceptionInfo();

        return !empty($this->codeceptionInfo['paths']['log']) ?
            $this->codeceptionInfo['paths']['log']
            : 'tests/_log';
    }

    /**
     * @return string[]
     */
    protected function getCodeceptionSuiteNames(): array
    {
        if (!$this->codeceptionSuiteNames) {
            $this->initCodeceptionInfo();

            $suiteFiles = Finder::create()
                ->in($this->codeceptionInfo['paths']['tests'])
                ->files()
                ->name('*.suite.yml')
                ->name('*.suite.dist.yml')
                ->depth(0);

            foreach ($suiteFiles as $suiteFile) {
                [$suitName] = explode('.', $suiteFile->getBasename());
                $this->codeceptionSuiteNames[] = $suitName;
            }

            $this->codeceptionSuiteNames = array_unique($this->codeceptionSuiteNames);
        }

        return $this->codeceptionSuiteNames;
    }

    /**
     * @param array<string> $suiteNames
     */
    protected function validateArgCodeceptionSuiteNames(array $suiteNames): void
    {
        if (!$suiteNames) {
            return;
        }

        $invalidSuiteNames = array_diff($suiteNames, $this->getCodeceptionSuiteNames());
        if ($invalidSuiteNames) {
            throw new \InvalidArgumentException(
                'The following Codeception suite names are invalid: ' . implode(', ', $invalidSuiteNames),
                1
            );
        }
    }

    protected function getProcessCallback(
        bool $hideStdOutput = false,
        bool $hideStdError = false
    ): \Closure {
        return function (string $type, string $data) use ($hideStdOutput, $hideStdError) {
            if (($type === Process::OUT && $hideStdOutput)
                || ($type === Process::ERR && $hideStdError)
            ) {
                return;
            }

            switch ($type) {
                case Process::OUT:
                    $this->output()->write($data);
                    break;

                case Process::ERR:
                    $this->errorOutput()->write($data);
                    break;
            }
        };
    }

    /**
     * @return array<string, template-robo-task-php-executable>
     */
    protected function getEnabledPhpExecutables(): array
    {
        /** @var array<string, template-robo-task-php-executable> $phpExecutables */
        $phpExecutables = array_filter(
            $this->getConfig()->get('php.executables'),
            fn(array $php): bool => !empty($php['enabled']),
        );

        return $phpExecutables;
    }
}
