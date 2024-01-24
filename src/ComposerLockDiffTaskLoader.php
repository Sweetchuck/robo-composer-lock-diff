<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff;

use Robo\Collection\CollectionBuilder;

trait ComposerLockDiffTaskLoader
{
    /**
     * @return \Sweetchuck\Robo\ComposerLockDiff\Task\ReportTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskComposerLockDiffList(): CollectionBuilder
    {
        /** @var \Sweetchuck\Robo\ComposerLockDiff\Task\ReportTask $task */
        $task = $this->task(Task\ReportTask::class);
        $task->setContainer($this->getContainer());

        return $task;
    }
}
