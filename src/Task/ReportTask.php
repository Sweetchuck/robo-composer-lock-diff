<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Task;

use Psr\Log\NullLogger;
use Sweetchuck\ComposerLockDiff\ReporterInterface;

class ReportTask extends BaseTask
{
    protected string $taskName = 'Composer Lock Diff - Report';

    protected ?ReporterInterface $reporter = null;

    public function getReporter(): ?ReporterInterface
    {
        return $this->reporter;
    }

    public function setReporter(ReporterInterface $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    /**
     * @phpstan-var array<\Sweetchuck\ComposerLockDiff\LockDiffEntry>
     */
    protected array $entries = [];

    /**
     * @phpstan-return array<\Sweetchuck\ComposerLockDiff\LockDiffEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @phpstan-param array<\Sweetchuck\ComposerLockDiff\LockDiffEntry> $entries
     */
    public function setEntries(array $entries): static
    {
        $this->entries = $entries;

        return $this;
    }

    /**
     * @phpstan-param robo-composer-lock-diff-report-task-options $options
     */
    public function setOptions(array $options): static
    {
        parent::setOptions($options);

        if (array_key_exists('reporter', $options)) {
            $this->setReporter($options['reporter']);
        }

        if (array_key_exists('entries', $options)) {
            $this->setEntries($options['entries']);
        }

        return $this;
    }

    protected function runDoIt(): static
    {
        $logger = $this->logger ?: new NullLogger();
        $reporter = $this->getReporter();
        $entries = $this->getEntries();
        if (!$reporter && !$entries) {
            $logger->warning('Both reporter and entries are missing');

            return $this;
        }

        if (!$reporter) {
            $this->taskResultCode = 1;
            $this->taskResultMessage = 'Missing reporter';

            return $this;
        }

        $reporter->generate($entries);

        return $this;
    }
}
