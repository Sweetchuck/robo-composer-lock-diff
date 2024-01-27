<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Tests\Unit\Task;

class ReportTaskTest extends TaskTestBase
{

    /**
     * @return \Robo\Collection\CollectionBuilder|\Sweetchuck\Robo\ComposerLockDiff\Task\ReportTask
     */
    protected function createTaskInstance()
    {
        return $this->taskBuilder->taskComposerLockDiffReport();
    }

    public function testRun(): void
    {
        $task = $this->createTaskInstance();
        $result = $task->run();
        $this->tester->assertSame(0, $result->getExitCode());
    }
}
