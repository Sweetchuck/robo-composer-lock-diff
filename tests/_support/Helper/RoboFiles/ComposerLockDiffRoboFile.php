<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Tests\Helper\RoboFiles;

use Robo\Tasks;
use Sweetchuck\Robo\ComposerLockDiff\ComposerLockDiffTaskLoader;
use Robo\Contract\TaskInterface;

class ComposerLockDiffRoboFile extends Tasks
{
    use ComposerLockDiffTaskLoader;

    protected function output()
    {
        return $this->getContainer()->get('output');
    }

    /**
     * @command template:list
     */
    public function templateList(): TaskInterface
    {
        return $this
            ->taskComposerLockDiffList()
            ->setRaw(true);
    }
}
