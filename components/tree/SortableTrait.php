<?php

namespace app\components\tree;

use Throwable;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;

/**
 * @see ActiveRecord
 */
trait SortableTrait
{
    private null|SortableBehavior $_sortableBehavior = null;

    /**
     * @return integer
     * @throws InvalidConfigException
     */
    public function getSortablePosition(): int
    {
        return $this->getSortableBehavior()->getSortablePosition();
    }

    /**
     * @return SortableBehavior
     * @throws InvalidConfigException
     */
    private function getSortableBehavior(): SortableBehavior
    {
        if ($this->_sortableBehavior === null) {
            /** @var ActiveRecord|Component|self $this */
            foreach ($this->getBehaviors() as $behavior) {
                if ($behavior instanceof SortableBehavior) {
                    $this->_sortableBehavior = $behavior;
                }
            }
            if ($this->_sortableBehavior === null) {
                throw new InvalidConfigException('SortableBehavior is not attached to model');
            }
        }
        return $this->_sortableBehavior;
    }

    /**
     * @return ActiveRecord
     * @throws InvalidConfigException
     */
    public function moveFirst(): ActiveRecord
    {
        return $this->getSortableBehavior()->moveFirst();
    }

    /**
     * @return ActiveRecord
     * @throws InvalidConfigException
     */
    public function moveLast(): ActiveRecord
    {
        return $this->getSortableBehavior()->moveLast();
    }

    /**
     * @param integer $position
     * @param bool $forward Move existing items to forward or backward
     * @return ActiveRecord
     * @throws InvalidConfigException
     */
    public function moveTo(int $position, bool $forward = true): ActiveRecord
    {
        return $this->getSortableBehavior()->moveTo($position, $forward);
    }

    /**
     * @param ActiveRecord $model
     * @return ActiveRecord
     * @throws InvalidConfigException
     */
    public function moveBefore(ActiveRecord $model): ActiveRecord
    {
        return $this->getSortableBehavior()->moveBefore($model);
    }

    /**
     * @param ActiveRecord $model
     * @return ActiveRecord
     * @throws InvalidConfigException
     */
    public function moveAfter(ActiveRecord $model): ActiveRecord
    {
        return $this->getSortableBehavior()->moveAfter($model);
    }

    /**
     * Reorders items with values of sortAttribute begin from zero.
     * @param bool $middle
     * @return integer
     * @throws Throwable
     */
    public function reorder(bool $middle = true): int
    {
        return $this->getSortableBehavior()->reorder($middle);
    }
}