<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Tests\Acceptance\Task;

use Sweetchuck\Robo\ComposerLockDiff\Tests\AcceptanceTester;
use Sweetchuck\Robo\ComposerLockDiff\Tests\Helper\RoboFiles\ComposerLockDiffRoboFile;

class ListTaskCest
{
    public function runList(AcceptanceTester $I): void
    {
        $id = 'template:list';
        $I->runRoboTask($id, ComposerLockDiffRoboFile::class, 'template:list');
        $exitCode = $I->getRoboTaskExitCode($id);
        $stdOutput = $I->getRoboTaskStdOutput($id);
        $stdError = $I->getRoboTaskStdError($id);

        $I->assertSame(0, $exitCode);
        $I->assertStringContainsString('List commands', $stdOutput);
        $I->assertStringContainsString('vendor/bin/robo list --raw', $stdError);
    }
}
