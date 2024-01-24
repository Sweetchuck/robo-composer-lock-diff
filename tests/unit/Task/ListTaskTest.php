<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Tests\Unit\Task;

use Sweetchuck\Codeception\Module\RoboTaskRunner\DummyProcess;
use Sweetchuck\Robo\ComposerLockDiff\Task\ReportTask;

/**
 * @method ReportTask createTask()
 */
class ListTaskTest extends CliTaskTestBase
{

    /**
     * @return \Robo\Collection\CollectionBuilder|\Sweetchuck\Robo\ComposerLockDiff\Task\ReportTask
     */
    protected function createTaskInstance()
    {
        return $this->taskBuilder->taskTemplateList();
    }

    public function casesGetCommand(): array
    {
        return [
            'basic' => [
                'vendor/bin/robo list',
                [],
            ],
        ];
    }

    public function testRun(): void
    {
        $task = $this->createTask();
        DummyProcess::$prophecy[] = [
            'exitCode' => 0,
            'stdOutput' => '',
            'stdError' => '',
        ];
        $result = $task->run();
        $this->tester->assertSame(0, $result->getExitCode());
    }
}
