<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Task;

use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\Common\OutputAwareTrait;
use Consolidation\AnnotatedCommand\Output\OutputAwareInterface;
use Robo\Result;
use Robo\Task\BaseTask as RoboBaseTask;
use Robo\TaskInfo;

abstract class BaseTask extends RoboBaseTask implements ContainerAwareInterface, OutputAwareInterface
{
    use ContainerAwareTrait;
    use OutputAwareTrait;

    /**
     * @override
     */
    protected string $taskName = 'Composer Lock Diff';

    /**
     * @var array<string, mixed>
     */
    protected array $assets = [];

    // region Options

    // region Option - assetNamePrefix.
    /**
     * @var string
     */
    protected string $assetNamePrefix = '';

    public function getAssetNamePrefix(): string
    {
        return $this->assetNamePrefix;
    }

    public function setAssetNamePrefix(string $value): static
    {
        $this->assetNamePrefix = $value;

        return $this;
    }
    // endregion

    // endregion

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): static
    {
        if (array_key_exists('assetNamePrefix', $options)) {
            $this->setAssetNamePrefix($options['assetNamePrefix']);
        }

        return $this;
    }

    protected int $taskResultCode = 0;

    protected function getTaskResultCode(): int
    {
        return $this->taskResultCode;
    }

    protected string $taskResultMessage = '';

    protected function getTaskResultMessage(): string
    {
        return $this->taskResultMessage;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Robo\Result<string, mixed>
     */
    public function run()
    {
        return $this
            ->runInit()
            ->runHeader()
            ->runDoIt()
            ->runProcessOutputs()
            ->runReturn();
    }

    protected function runInit(): static
    {
        $this->taskResultCode = 0;
        $this->taskResultMessage = '';

        return $this;
    }

    protected function runHeader(): static
    {
        $this->printTaskInfo($this->taskName);

        return $this;
    }

    abstract protected function runDoIt(): static;

    protected function runProcessOutputs(): static
    {
        return $this;
    }

    /**
     * @return \Robo\Result<string, mixed>
     */
    protected function runReturn(): Result
    {
        return new Result(
            $this,
            $this->getTaskResultCode(),
            $this->getTaskResultMessage(),
            $this->getAssetsWithPrefixedNames()
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAssetsWithPrefixedNames(): array
    {
        $prefix = $this->getAssetNamePrefix();
        if (!$prefix) {
            return $this->assets;
        }

        $assets = [];
        foreach ($this->assets as $key => $value) {
            $assets["{$prefix}{$key}"] = $value;
        }

        return $assets;
    }

    public function getTaskName(): string
    {
        return $this->taskName ?: TaskInfo::formatTaskName($this);
    }

    /**
     * {@inheritdoc}
     *
     * @param null|array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function getTaskContext($context = null)
    {
        if (!$context) {
            $context = [];
        }

        if (empty($context['name'])) {
            $context['name'] = $this->getTaskName();
        }

        return parent::getTaskContext($context);
    }
}
