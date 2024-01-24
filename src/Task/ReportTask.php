<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Task;

class ReportTask extends BaseTask
{
    protected string $taskName = 'Composer Lock Diff - Report';

    protected function runDoIt(): static {
        return $this;
    }
}
