<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Tests\Helper\RoboFiles;

use Robo\Tasks;
use Sweetchuck\ComposerLockDiff\Reporter\ConsoleTableReporter;
use Sweetchuck\Robo\ComposerLockDiff\ComposerLockDiffTaskLoader;
use Robo\Contract\TaskInterface;
use Robo\State\Data as RoboState;
use Symfony\Component\Console\Helper\Table;

class ComposerLockDiffRoboFile extends Tasks
{
    use ComposerLockDiffTaskLoader;

    protected function output()
    {
        return $this->getContainer()->get('output');
    }

    /**
     * @command composer-lock-diff:report
     */
    public function cmdComposerLockDiffReportExecute(): TaskInterface
    {
        $cb = $this->collectionBuilder();
        $cb->addCode(function (RoboState $state): int {
            $state['leftLock'] = [
                'packages' => [
                    [
                        'name' => 'a/b',
                        'version' => '1.2.3',
                    ],
                ],
            ];
            $state['rightLock'] = [
                'packages' => [
                    [
                        'name' => 'a/b',
                        'version' => '1.2.3',
                    ],
                ],
            ];
            $state['leftJson'] = [
                'require' => [],
            ];
            $state['rightJson'] = [
                'require' => [
                    'a/b' => '^1.0',
                ],
            ];

            $reporter = new ConsoleTableReporter();
            $reporter->setTable(new Table($this->output()));
            $state['reporter'] = $reporter;

            return 0;
        });
        $cb->addTask(
            $this
                ->taskComposerLockDiffer()
                ->deferTaskConfiguration('setLeftLock', 'leftLock')
                ->deferTaskConfiguration('setRightLock', 'rightLock')
                ->deferTaskConfiguration('setLeftJson', 'leftJson')
                ->deferTaskConfiguration('setRightJson', 'rightJson')
        );
        $cb->addTask(
            $this
                ->taskComposerLockDiffReport()
                ->deferTaskConfiguration('setReporter', 'reporter')
                ->deferTaskConfiguration('setEntries', 'composer_lock_differ.entries')
        );

        return $cb;
    }
}
