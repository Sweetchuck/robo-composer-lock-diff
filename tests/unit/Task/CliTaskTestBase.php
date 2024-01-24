<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Tests\Unit\Task;

use Codeception\Attribute\DataProvider;
use League\Container\Container as LeagueContainer;
use Psr\Container\ContainerInterface;
use Robo\Application;
use Robo\Collection\CollectionBuilder;
use Robo\Config\Config;
use Robo\Config\Config as RoboConfig;
use Robo\Robo;
use Sweetchuck\Codeception\Module\RoboTaskRunner\DummyOutput;
use Sweetchuck\Codeception\Module\RoboTaskRunner\DummyProcess;
use Sweetchuck\Codeception\Module\RoboTaskRunner\DummyProcessHelper;
use Sweetchuck\Robo\ComposerLockDiff\Tests\Helper\Dummy\DummyTaskBuilder;
use Sweetchuck\Robo\ComposerLockDiff\Tests\Unit\TestBase;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;

abstract class CliTaskTestBase extends TestBase
{
    protected ContainerInterface $container;

    protected RoboConfig $config;

    protected CollectionBuilder $builder;

    protected DummyTaskBuilder $taskBuilder;

    /**
     * @retrun void
     * @phpstan-return void
     */
    public function _before()
    {
        parent::_before();

        DummyProcess::reset();

        Robo::unsetContainer();
        $this->container = new LeagueContainer();
        $application = new SymfonyApplication('Sweetchuck - Robo PHPUnit', '3.0.0');
        $application->getHelperSet()->set(new DummyProcessHelper(), 'process');
        $this->config = new Config();
        $input = null;
        $output = new DummyOutput([
            'verbosity' => OutputInterface::VERBOSITY_DEBUG,
        ]);

        $this->container->add('container', $this->container);

        Robo::configureContainer($this->container, $application, $this->config, $input, $output);
        $this->container->add('logger', BufferingLogger::class);

        $this->builder = new CollectionBuilder(null);
        $this->taskBuilder = new DummyTaskBuilder();
        $this->taskBuilder->setBuilder($this->builder);
        $this->taskBuilder->setContainer($this->container);
    }

    /**
     * @return \Sweetchuck\Robo\ComposerLockDiff\Task\BaseCliTask
     */
    protected function createTask()
    {
        $container = new LeagueContainer();
        $application = new Application('Sweetchuck - Robo PHPLint', '1.0.0');
        $application->getHelperSet()->set(new DummyProcessHelper(), 'process');
        $config = new Config();
        $output = new DummyOutput([]);
        $loggerOutput = new DummyOutput([]);
        $logger = new ConsoleLogger($loggerOutput);

        $container->add('output', $output);
        $container->add('logger', $logger);
        $container->add('config', $config);
        $container->add('application', $application);

        $task = $this->createTaskInstance();
        $task->setContainer($container);
        $task->setOutput($output);
        $task->setLogger($logger);

        return $task;
    }

    /**
     * @return \Sweetchuck\Robo\ComposerLockDiff\Task\BaseCliTask
     */
    abstract protected function createTaskInstance();

    /**
     * @return array<string, mixed>
     */
    abstract public function casesGetCommand(): array;

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('casesGetCommand')]
    public function testGetCommand(string $expected, array $options): void
    {
        $task = $this->createTask();
        $task->setOptions($options);
        $this->tester->assertSame(
            $expected,
            $task->getCommand(),
        );
    }
}
