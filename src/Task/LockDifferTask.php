<?php

declare(strict_types = 1);

namespace Sweetchuck\Robo\ComposerLockDiff\Task;

use Sweetchuck\ComposerLockDiff\LockDiffer;

class LockDifferTask extends BaseTask
{
    protected string $taskName = 'Composer Lock Diff - LockDiffer';

    protected ?LockDiffer $lockDiffer = null;

    public function getLockDiffer(): ?LockDiffer
    {
        return $this->lockDiffer;
    }

    public function setLockDiffer(?LockDiffer $lockDiffer): static
    {
        $this->lockDiffer = $lockDiffer;

        return $this;
    }

    /**
     * @phpstan-var null|composer-lock-diff-lock
     */
    protected ?array $leftLock = null;

    /**
     * @phpstan-return null|composer-lock-diff-lock
     */
    public function getLeftLock(): ?array
    {
        return $this->leftLock;
    }

    /**
     * @phpstan-param null|composer-lock-diff-lock $leftLock
     */
    public function setLeftLock(?array $leftLock): static
    {
        $this->leftLock = $leftLock;

        return $this;
    }

    /**
     * @phpstan-var null|composer-lock-diff-lock
     */
    protected ?array $rightLock = null;

    /**
     * @phpstan-return null|composer-lock-diff-lock
     */
    public function getRightLock(): ?array
    {
        return $this->rightLock;
    }

    /**
     * @phpstan-param null|composer-lock-diff-lock $rightLock
     */
    public function setRightLock(?array $rightLock): static
    {
        $this->rightLock = $rightLock;

        return $this;
    }

    /**
     * @phpstan-var null|composer-lock-diff-json
     */
    protected ?array $leftJson = null;

    /**
     * @phpstan-return null|composer-lock-diff-json
     */
    public function getLeftJson(): ?array
    {
        return $this->leftJson;
    }

    /**
     * @phpstan-param null|composer-lock-diff-json $leftJson
     */
    public function setLeftJson(?array $leftJson): static
    {
        $this->leftJson = $leftJson;

        return $this;
    }

    /**
     * @phpstan-var null|composer-lock-diff-json
     */
    protected ?array $rightJson = null;

    /**
     * @phpstan-return null|composer-lock-diff-json
     */
    public function getRightJson(): ?array
    {
        return $this->rightJson;
    }

    /**
     * @phpstan-param null|composer-lock-diff-json $rightJson
     */
    public function setRightJson(?array $rightJson): static
    {
        $this->rightJson = $rightJson;

        return $this;
    }

    /**
     * @phpstan-param robo-composer-lock-diff-lockdiffer-task-options $options
     */
    public function setOptions(array $options): static
    {
        parent::setOptions($options);

        if (array_key_exists('lockDiffer', $options)) {
            $this->setLockDiffer($options['lockDiffer']);
        }

        if (array_key_exists('leftLock', $options)) {
            $this->setLeftLock($options['leftLock']);
        }

        if (array_key_exists('rightLock', $options)) {
            $this->setRightLock($options['rightLock']);
        }

        if (array_key_exists('leftJson', $options)) {
            $this->setLeftJson($options['leftJson']);
        }

        if (array_key_exists('rightJson', $options)) {
            $this->setRightJson($options['rightJson']);
        }

        return $this;
    }

    protected function runDoIt(): static
    {
        $lockDiffer = $this->getLockDiffer() ?: new LockDiffer();
        $this->assets['composer_lock_differ.entries'] = $lockDiffer->diff(
            $this->getLeftLock(),
            $this->getRightLock(),
            $this->getLeftJson(),
            $this->getRightJson(),
        );

        return $this;
    }
}
