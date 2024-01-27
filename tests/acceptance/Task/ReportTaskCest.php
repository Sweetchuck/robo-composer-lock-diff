<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Tests\Acceptance\Task;

use Sweetchuck\Robo\ComposerLockDiff\Tests\AcceptanceTester;
use Sweetchuck\Robo\ComposerLockDiff\Tests\Helper\RoboFiles\ComposerLockDiffRoboFile;

class ReportTaskCest
{
    public function runComposerLockDiffReport(AcceptanceTester $I): void
    {
        $id = 'template:list';
        $I->runRoboTask($id, ComposerLockDiffRoboFile::class, 'composer-lock-diff:report');
        $exitCode = $I->getRoboTaskExitCode($id);
        $stdOutput = $I->getRoboTaskStdOutput($id);
        $stdError = $I->getRoboTaskStdError($id);

        $expectedStdOutput = <<<'TEXT'
        +------+--------+-------+-------------+-----------------+
        | Name | Before | After | Required    | Direct          |
        +------+--------+-------+-------------+-----------------+
        | a/b  | 1.2.3  | 1.2.3 | prod : prod | child  : direct |
        +------+--------+-------+-------------+-----------------+

        TEXT;

        $I->assertSame(0, $exitCode, 'exit code');
        $I->assertEquals($expectedStdOutput, $stdOutput, 'stdOutput');
        $I->assertStringContainsString('[Composer Lock Diff - LockDiffer]', $stdError, 'stdError');
        $I->assertStringContainsString('[Composer Lock Diff - Report]', $stdError, 'stdError');
    }
}
