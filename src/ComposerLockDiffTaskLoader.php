<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff;

use Robo\Collection\CollectionBuilder;

trait ComposerLockDiffTaskLoader
{
    /**
     * @phpstan-param robo-composer-lock-diff-lockdiffer-task-options $options
     *
     * @return \Sweetchuck\Robo\ComposerLockDiff\Task\LockDifferTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskComposerLockDiffer(array $options = []): CollectionBuilder
    {
        /** @var \Sweetchuck\Robo\ComposerLockDiff\Task\LockDifferTask $task */
        $task = $this->task(Task\LockDifferTask::class);
        $task->setContainer($this->getContainer());
        $task->setOptions($options);

        return $task;
    }

    /**
     * @phpstan-param robo-composer-lock-diff-report-task-options $options
     *
     * @return \Sweetchuck\Robo\ComposerLockDiff\Task\ReportTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskComposerLockDiffReport(array $options = []): CollectionBuilder
    {
        /** @var \Sweetchuck\Robo\ComposerLockDiff\Task\ReportTask $task */
        $task = $this->task(Task\ReportTask::class);
        $task->setContainer($this->getContainer());
        $task->setOptions($options);

        return $task;
    }
}
