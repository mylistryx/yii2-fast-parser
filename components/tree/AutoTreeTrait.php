<?php

namespace app\components\tree;

use Throwable;
use yii\base\Behavior;
use yii\base\Component;
use yii\base\InvalidCallException;
use yii\db\ActiveQuery;
use yii\db\BaseActiveRecord;

trait AutoTreeTrait
{
    /**
     * @var Behavior[]
     */
    private ?array $_autoTreeMap = null;

    private bool $_autoTreeReturnBehavior = false;

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        /** @var BaseActiveRecord|self $this */
        // replace getParents() by getParentsOrdered() if behavior not support ordered query
        if ($name === 'parents') {
            $this->_autoTreeReturnBehavior = true;
            $behavior = $this->getParents();
            $this->_autoTreeReturnBehavior = false;
            if (method_exists($behavior, 'getParentsOrdered')) {
                $this->populateRelation($name, $behavior->getParentsOrdered());
            }
        }
        if ($name === 'descendants') {
            $this->_autoTreeReturnBehavior = true;
            $behavior = $this->getDescendants();
            $this->_autoTreeReturnBehavior = false;
            if (method_exists($behavior, 'getDescendantsOrdered')) {
                $this->populateRelation($name, $behavior->getDescendantsOrdered());
            }
        }
        return parent::__get($name);
    }

    /**
     * @param int|null $depth
     * @return ActiveQuery
     */
    public function getParents(?int $depth = null): ActiveQuery
    {
        return $this->autoTreeCall('getParents', ['ns', 'al'], [$depth]);
    }

    /**
     * @param string $method
     * @param array $list
     * @param array $arguments
     * @param bool $firstOnly
     * @return mixed
     */
    private function autoTreeCall(string $method, array $list = [], array $arguments = [], bool $firstOnly = true): mixed
    {
        if ($this->_autoTreeMap === null) {
            $this->autoTreeInit();
        }

        $result = null;
        $founded = false;
        foreach ($list as $alias) {
            if (isset($this->_autoTreeMap[$alias])) {
                $behavior = $this->_autoTreeMap[$alias];
                if (method_exists($behavior, $method)) {
                    if ($this->_autoTreeReturnBehavior) {
                        return $behavior;
                    }
                    $founded = true;
                    $result = call_user_func_array([$behavior, $method], $arguments);
                    if ($firstOnly) {
                        return $result;
                    }
                }
            }
        }

        if (!$founded) {
            throw new InvalidCallException("Method '{$method}' not founded");
        }

        return $result;
    }

    /**
     * @return void
     */
    private function autoTreeInit(): void
    {
        /** @var Component|self $this */
        $aliases = $this->autoTreeAliases();
        foreach ($this->getBehaviors() as $behavior) {
            $className = $behavior::class;
            if (isset($aliases[$className]) && !isset($this->_autoTreeMap[$aliases[$className]])) {
                $this->_autoTreeMap[$aliases[$className]] = $behavior;
            }
        }
    }

    /**
     * @return array
     */
    private static function autoTreeAliases(): array
    {
        return [
            AdjacencyListBehavior::class => 'al',
            NestedSetsBehavior::class => 'ns',
        ];
    }

    /**
     * @param int|null $depth
     * @param bool $andSelf
     * @return ActiveQuery
     */
    public function getDescendants(?int $depth = null, bool $andSelf = false): ActiveQuery
    {
        return $this->autoTreeCall('getDescendants', ['ns', 'al'], [$depth, $andSelf]);
    }

    /**
     * @return ActiveQuery
     */
    public function getParent(): ActiveQuery
    {
        return $this->autoTreeCall('getParent', ['al', 'ns']);
    }

    /**
     * @return ActiveQuery
     */
    public function getRoot(): ActiveQuery
    {
        return $this->autoTreeCall('getRoot', ['ns', 'al']);
    }

    /**
     * @return ActiveQuery
     */
    public function getChildren(): ActiveQuery
    {
        return $this->autoTreeCall('getChildren', ['al', 'ns']);
    }

    /**
     * @param int|null $depth
     * @return ActiveQuery
     */
    public function getLeaves(?int $depth = null): ActiveQuery
    {
        return $this->autoTreeCall('getLeaves', ['ns', 'al'], [$depth]);
    }

    /**
     * @return ActiveQuery
     */
    public function getPrev(): ActiveQuery
    {
        return $this->autoTreeCall('getPrev', ['ns', 'al']);
    }

    /**
     * @return ActiveQuery
     */
    public function getNext(): ActiveQuery
    {
        return $this->autoTreeCall('getNext', ['ns', 'al']);
    }

    /**
     * Populate children relations for self and all descendants
     * @param int|null $depth = null
     * @param null $with = null
     * @return self
     */
    public function populateTree(?int $depth = null, $with = null): static
    {
        return $this->autoTreeCall('populateTree', ['ns', 'al'], [$depth, $with]);
    }

    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->autoTreeCall('isRoot', ['al', 'ns']);
    }

    /**
     * @param BaseActiveRecord $node
     * @return bool
     */
    public function isChildOf(BaseActiveRecord $node): bool
    {
        return $this->autoTreeCall('isChildOf', ['ns', 'al'], [$node]);
    }

    /**
     * @return bool
     */
    public function isLeaf(): bool
    {
        return $this->autoTreeCall('isLeaf', ['ns', 'al']);
    }

    /**
     * @return $this
     */
    public function makeRoot(): static
    {
        return $this->autoTreeCall('makeRoot', ['al', 'ns'], [], false);
    }

    /**
     * @param BaseActiveRecord $node
     * @return $this
     */
    public function prependTo(BaseActiveRecord $node): static
    {
        return $this->autoTreeCall('prependTo', ['al', 'ns'], [$node], false);
    }

    /**
     * @param BaseActiveRecord $node
     * @return $this
     */
    public function appendTo(BaseActiveRecord $node): static
    {
        return $this->autoTreeCall('appendTo', ['al', 'ns'], [$node], false);
    }

    /**
     * @param BaseActiveRecord $node
     * @return $this
     */
    public function insertBefore(BaseActiveRecord $node): static
    {
        return $this->autoTreeCall('insertBefore', ['al', 'ns'], [$node], false);
    }

    /**
     * @param BaseActiveRecord $node
     * @return $this
     */
    public function insertAfter(BaseActiveRecord $node): static
    {
        return $this->autoTreeCall('insertAfter', ['al', 'ns'], [$node], false);
    }

    /**
     * @return bool|int
     * @throws Throwable
     */
    public function deleteWithChildren(): bool|int
    {
        /** @var BaseActiveRecord|self $this */
        $this->autoTreeCall('preDeleteWithChildren', ['ns', 'al'], [], false);
        return $this->autoTreeCall('deleteWithChildren', ['ns', 'al']);
    }
}