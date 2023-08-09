<?php

namespace app\components\tree;

use Throwable;
use yii\base\Behavior;
use yii\base\Component;
use yii\base\NotSupportedException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use yii\db\Exception;
use yii\db\Expression;

/**
 *
 * @property-read ActiveQuery $next
 * @property-read ActiveQuery $parent
 * @property-read ActiveQuery $children
 * @property-read ActiveQuery $root
 * @property-read ActiveQuery $prev
 */
class NestedSetsBehavior extends Behavior
{
    const OPERATION_MAKE_ROOT = 1;
    const OPERATION_PREPEND_TO = 2;
    const OPERATION_APPEND_TO = 3;
    const OPERATION_INSERT_BEFORE = 4;
    const OPERATION_INSERT_AFTER = 5;
    const OPERATION_DELETE_ALL = 6;

    /**
     * @var ActiveRecord|Component|static|null the owner of this behavior
     */
    public $owner;

    /**
     * @var string|null
     */
    public ?string $treeAttribute = null;

    /**
     * @var string
     */
    public string $leftAttribute = 'lft';

    /**
     * @var string
     */
    public string $rightAttribute = 'rgt';

    /**
     * @var string
     */
    public string $depthAttribute = 'depth';

    /**
     * @var null|int
     */
    protected ?int $operation = null;

    /**
     * @var ActiveRecord|self|null
     */
    protected ActiveRecord|null|self $node = null;

    /**
     * @var null|string
     */
    protected ?string $treeChange = null;


    /**
     * @inheritdoc
     */
    public function events(): array
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeInsert',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getParent(): ActiveQuery
    {
        $tableName = $this->owner->tableName();
        $query = $this->getParents(1)
            ->orderBy(["$tableName.[[$this->leftAttribute]]" => SORT_DESC])
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @param int|null $depth
     * @return ActiveQuery
     */
    public function getParents(?int $depth = null): ActiveQuery
    {
        $tableName = $this->owner->tableName();
        $condition = [
            'and',
            ['<', "$tableName.[[$this->leftAttribute]]", $this->owner->getAttribute($this->leftAttribute)],
            ['>', "$tableName.[[$this->rightAttribute]]", $this->owner->getAttribute($this->rightAttribute)],
        ];
        if ($depth !== null) {
            $condition[] = ['>=', "$tableName.[[$this->depthAttribute]]", $this->owner->getAttribute($this->depthAttribute) - $depth];
        }

        $query = $this->owner->find()
            ->andWhere($condition)
            ->andWhere($this->treeCondition())
            ->addOrderBy(["$tableName.[[$this->leftAttribute]]" => SORT_ASC]);
        $query->multiple = true;

        return $query;
    }

    /**
     * @return array
     */
    protected function treeCondition(): array
    {
        $tableName = $this->owner->tableName();
        if ($this->treeAttribute === null) {
            return [];
        } else {
            return ["{$tableName}.[[{$this->treeAttribute}]]" => $this->owner->getAttribute($this->treeAttribute)];
        }
    }

    /**
     * @return ActiveQuery
     */
    public function getRoot(): ActiveQuery
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere(["$tableName.[[$this->leftAttribute]]" => 1])
            ->andWhere($this->treeCondition())
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @return ActiveQuery
     */
    public function getChildren(): ActiveQuery
    {
        return $this->getDescendants(1);
    }

    /**
     * @param int|null $depth
     * @param bool $andSelf
     * @param bool $backOrder
     * @return ActiveQuery
     */
    public function getDescendants(?int $depth = null, bool $andSelf = false, bool $backOrder = false): ActiveQuery
    {
        $tableName = $this->owner->tableName();
        $attribute = $backOrder ? $this->rightAttribute : $this->leftAttribute;
        $condition = [
            'and',
            [$andSelf ? '>=' : '>', "$tableName.[[$attribute]]", $this->owner->getAttribute($this->leftAttribute)],
            [$andSelf ? '<=' : '<', "$tableName.[[$attribute]]", $this->owner->getAttribute($this->rightAttribute)],
        ];

        if ($depth !== null) {
            $condition[] = ['<=', "$tableName.[[$this->depthAttribute]]", $this->owner->getAttribute($this->depthAttribute) + $depth];
        }

        $query = $this->owner->find()
            ->andWhere($condition)
            ->andWhere($this->treeCondition())
            ->addOrderBy(["$tableName.[[$attribute]]" => $backOrder ? SORT_DESC : SORT_ASC]);
        $query->multiple = true;

        return $query;
    }

    /**
     * @param int|null $depth
     * @return ActiveQuery
     */
    public function getLeaves(?int $depth = null): ActiveQuery
    {
        $tableName = $this->owner->tableName();
        $query = $this->getDescendants($depth)
            ->andWhere(["$tableName.[[$this->leftAttribute]]" => new Expression("$tableName.[[{$this->rightAttribute}]] - 1")]);
        $query->multiple = true;
        return $query;
    }

    /**
     * @return ActiveQuery
     */
    public function getPrev(): ActiveQuery
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere(["{$tableName}.[[$this->rightAttribute]]" => $this->owner->getAttribute($this->leftAttribute) - 1])
            ->andWhere($this->treeCondition())
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * @return ActiveQuery
     */
    public function getNext(): ActiveQuery
    {
        $tableName = $this->owner->tableName();
        $query = $this->owner->find()
            ->andWhere(["$tableName.[[{$this->leftAttribute}]]" => $this->owner->getAttribute($this->rightAttribute) + 1])
            ->andWhere($this->treeCondition())
            ->limit(1);
        $query->multiple = false;
        return $query;
    }

    /**
     * Populate children relations for self and all descendants
     * @param int|null $depth = null
     * @param string|array $with = null
     * @return ActiveRecord|Component|static|null
     */
    public function populateTree(?int $depth = null, $with = null): ActiveRecord|Component|static|null
    {
        /** @var ActiveRecord[]|static[] $nodes */
        $query = $this->getDescendants($depth);
        if ($with) {
            $query->with($with);
        }
        $nodes = $query->all();

        $key = $this->owner->getAttribute($this->leftAttribute);
        $relates = [];
        $parents = [$key];
        $prev = $this->owner->getAttribute($this->depthAttribute);
        foreach ($nodes as $node) {
            $level = $node->getAttribute($this->depthAttribute);
            if ($level <= $prev) {
                $parents = array_slice($parents, 0, $level - $prev - 1);
            }

            $key = end($parents);
            if (!isset($relates[$key])) {
                $relates[$key] = [];
            }
            $relates[$key][] = $node;

            $parents[] = $node->getAttribute($this->leftAttribute);
            $prev = $level;
        }

        $ownerDepth = $this->owner->getAttribute($this->depthAttribute);
        $nodes[] = $this->owner;
        foreach ($nodes as $node) {
            $key = $node->getAttribute($this->leftAttribute);
            if (isset($relates[$key])) {
                $node->populateRelation('children', $relates[$key]);
            } elseif ($depth === null || $ownerDepth + $depth > $node->getAttribute($this->depthAttribute)) {
                $node->populateRelation('children', []);
            }
        }

        return $this->owner;
    }

    /**
     * @return ActiveRecord|Component|static|null
     */
    public function makeRoot(): ActiveRecord|Component|static|null
    {
        $this->operation = self::OPERATION_MAKE_ROOT;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord|Component|static|null
     */
    public function prependTo(ActiveRecord $node): ActiveRecord|Component|static|null
    {
        $this->operation = self::OPERATION_PREPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord|Component|static|null
     */
    public function appendTo(ActiveRecord $node): ActiveRecord|Component|static|null
    {
        $this->operation = self::OPERATION_APPEND_TO;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord|Component|static|null
     */
    public function insertBefore(ActiveRecord $node): ActiveRecord|Component|static|null
    {
        $this->operation = self::OPERATION_INSERT_BEFORE;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * @param ActiveRecord $node
     * @return ActiveRecord|Component|static|null
     */
    public function insertAfter(ActiveRecord $node): ActiveRecord|Component|static|null
    {
        $this->operation = self::OPERATION_INSERT_AFTER;
        $this->node = $node;
        return $this->owner;
    }

    /**
     * Need for AutoTree
     */
    public function preDeleteWithChildren(): void
    {
        $this->operation = self::OPERATION_DELETE_ALL;
    }

    /**
     * @return bool|int
     * @throws Throwable
     * @throws Exception
     */
    public function deleteWithChildren(): bool|int
    {
        $this->operation = self::OPERATION_DELETE_ALL;
        if (!$this->owner->isTransactional(ActiveRecord::OP_DELETE)) {
            $transaction = $this->owner->getDb()->beginTransaction();
            try {
                $result = $this->deleteWithChildrenInternal();
                if ($result === false) {
                    $transaction->rollBack();
                } else {
                    $transaction->commit();
                }
                return $result;
            } catch (Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        } else {
            $result = $this->deleteWithChildrenInternal();
        }
        return $result;
    }

    /**
     * @return false|int
     * @throws Exception
     */
    protected function deleteWithChildrenInternal(): false|int
    {
        if (!$this->owner->beforeDelete()) {
            return false;
        }
        $result = $this->owner->deleteAll($this->getDescendants(null, true)->where);
        $this->owner->setOldAttributes(null);
        $this->owner->afterDelete();
        return $result;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function beforeDelete(): void
    {
        if ($this->owner->getIsNewRecord()) {
            throw new Exception('Can not delete a node when it is new record.');
        }
        if ($this->isRoot() && $this->operation !== self::OPERATION_DELETE_ALL) {
            throw new Exception('Method "' . $this->owner::class . '::delete" is not supported for deleting root nodes.');
        }
        $this->owner->refresh();
    }

    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->owner->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * @return void
     */
    public function afterDelete(): void
    {
        $left = $this->owner->getAttribute($this->leftAttribute);
        $right = $this->owner->getAttribute($this->rightAttribute);
        if ($this->operation === static::OPERATION_DELETE_ALL || $this->isLeaf()) {
            $this->shift($right + 1, null, $left - $right - 1);
        } else {
            $this->owner->updateAll(
                [
                    $this->leftAttribute => new Expression("[[{$this->leftAttribute}]] - 1"),
                    $this->rightAttribute => new Expression("[[{$this->rightAttribute}]] - 1"),
                    $this->depthAttribute => new Expression("[[{$this->depthAttribute}]] - 1"),
                ],
                $this->getDescendants()->where,
            );
            $this->shift($right + 1, null, -2);
        }
        $this->operation = null;
        $this->node = null;
    }

    /**
     * @return bool
     */
    public function isLeaf(): bool
    {
        return $this->owner->getAttribute($this->rightAttribute) - $this->owner->getAttribute($this->leftAttribute) === 1;
    }

    /**
     * @param int $from
     * @param null|int $to
     * @param int $delta
     * @param int|null $tree
     */
    protected function shift(int $from, ?int $to, int $delta, ?int $tree = null): void
    {
        if ($delta !== 0 && ($to === null || $to >= $from)) {
            if ($this->treeAttribute !== null && $tree === null) {
                $tree = $this->owner->getAttribute($this->treeAttribute);
            }
            foreach ([$this->leftAttribute, $this->rightAttribute] as $i => $attribute) {
                $this->owner->updateAll(
                    [$attribute => new Expression("[[{$attribute}]]" . sprintf('%+d', $delta))],
                    [
                        'and',
                        $to === null ? ['>=', $attribute, $from] : ['between', $attribute, $from, $to],
                        $this->treeAttribute !== null ? [$this->treeAttribute => $tree] : [],
                    ],
                );
            }
        }
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function beforeInsert(): void
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                $condition = array_merge([$this->leftAttribute => 1], $this->treeCondition());
                if ($this->owner->find()->andWhere($condition)->one() !== null) {
                    throw new Exception('Can not create more than one root.');
                }
                $this->owner->setAttribute($this->leftAttribute, 1);
                $this->owner->setAttribute($this->rightAttribute, 2);
                $this->owner->setAttribute($this->depthAttribute, 0);
                break;

            case self::OPERATION_PREPEND_TO:
                $this->insertNode($this->node->getAttribute($this->leftAttribute) + 1, 1);
                break;

            case self::OPERATION_APPEND_TO:
                $this->insertNode($this->node->getAttribute($this->rightAttribute), 1);
                break;

            case self::OPERATION_INSERT_BEFORE:
                $this->insertNode($this->node->getAttribute($this->leftAttribute), 0);
                break;

            case self::OPERATION_INSERT_AFTER:
                $this->insertNode($this->node->getAttribute($this->rightAttribute) + 1, 0);
                break;

            default:
                throw new NotSupportedException('Method "' . $this->owner->className() . '::insert" is not supported for inserting new nodes.');
        }
    }

    /**
     * @param int $to
     * @param int $depth
     * @throws Exception
     */
    protected function insertNode(int $to, int $depth = 0): void
    {
        if ($this->node->getIsNewRecord()) {
            throw new Exception('Can not create a node when the target node is new record.');
        }

        if ($depth === 0 && $this->node->isRoot()) {
            throw new Exception('Can not insert a node before/after root.');
        }
        $this->owner->setAttribute($this->leftAttribute, $to);
        $this->owner->setAttribute($this->rightAttribute, $to + 1);
        $this->owner->setAttribute($this->depthAttribute, $this->node->getAttribute($this->depthAttribute) + $depth);
        if ($this->treeAttribute !== null) {
            $this->owner->setAttribute($this->treeAttribute, $this->node->getAttribute($this->treeAttribute));
        }
        $this->shift($to, null, 2);
    }

    /**
     * @throws Exception
     */
    public function afterInsert(): void
    {
        if ($this->operation === self::OPERATION_MAKE_ROOT && $this->treeAttribute !== null && $this->owner->getAttribute($this->treeAttribute) === null) {
            $id = $this->owner->getPrimaryKey();
            $this->owner->setAttribute($this->treeAttribute, $id);

            $primaryKey = $this->owner->primaryKey();
            if (!isset($primaryKey[0])) {
                throw new Exception('"' . $this->owner::class . '" must have a primary key.');
            }

            $this->owner->updateAll([$this->treeAttribute => $id], [$primaryKey[0] => $id]);
        }
        $this->operation = null;
        $this->node = null;
    }

    /**
     * @throws Exception
     */
    public function beforeUpdate(): void
    {
        if ($this->node !== null && !$this->node->getIsNewRecord()) {
            $this->node->refresh();
        }

        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeAttribute === null) {
                    throw new Exception('Can not move a node as the root when "treeAttribute" is not set.');
                }
                if ($this->owner->getOldAttribute($this->treeAttribute) !== $this->owner->getAttribute($this->treeAttribute)) {
                    $this->treeChange = $this->owner->getAttribute($this->treeAttribute);
                    $this->owner->setAttribute($this->treeAttribute, $this->owner->getOldAttribute($this->treeAttribute));
                }
                break;

            case self::OPERATION_INSERT_BEFORE:
            case self::OPERATION_INSERT_AFTER:
                if ($this->node->isRoot()) {
                    throw new Exception('Can not move a node before/after root.');
                }

            case self::OPERATION_PREPEND_TO:
            case self::OPERATION_APPEND_TO:
                if ($this->node->getIsNewRecord()) {
                    throw new Exception('Can not move a node when the target node is new record.');
                }

                if ($this->owner->equals($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }

                if ($this->node->isChildOf($this->owner)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    /**
     * @param ActiveRecord $node
     * @return bool
     */
    public function isChildOf(ActiveRecord $node): bool
    {
        $result = $this->owner->getAttribute($this->leftAttribute) > $node->getAttribute($this->leftAttribute)
            && $this->owner->getAttribute($this->rightAttribute) < $node->getAttribute($this->rightAttribute);

        if ($result && $this->treeAttribute !== null) {
            $result = $this->owner->getAttribute($this->treeAttribute) === $node->getAttribute($this->treeAttribute);
        }

        return $result;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function afterUpdate(): void
    {
        switch ($this->operation) {
            case self::OPERATION_MAKE_ROOT:
                if ($this->treeChange || !$this->isRoot() || $this->owner->getIsNewRecord()) {
                    $this->moveNodeAsRoot();
                }
                break;

            case self::OPERATION_PREPEND_TO:
                $this->moveNode($this->node->getAttribute($this->leftAttribute) + 1, 1);
                break;

            case self::OPERATION_APPEND_TO:
                $this->moveNode($this->node->getAttribute($this->rightAttribute), 1);
                break;

            case self::OPERATION_INSERT_BEFORE:
                $this->moveNode($this->node->getAttribute($this->leftAttribute), 0);
                break;

            case self::OPERATION_INSERT_AFTER:
                $this->moveNode($this->node->getAttribute($this->rightAttribute) + 1, 0);
                break;
        }
        $this->operation = null;
        $this->node = null;
        $this->treeChange = null;
    }

    /**
     *
     */
    protected function moveNodeAsRoot(): void
    {
        $left = $this->owner->getAttribute($this->leftAttribute);
        $right = $this->owner->getAttribute($this->rightAttribute);
        $depth = $this->owner->getAttribute($this->depthAttribute);
        $tree = $this->treeChange ? $this->treeChange : $this->owner->getPrimaryKey();

        $this->owner->updateAll(
            [
                $this->leftAttribute => new Expression("[[{$this->leftAttribute}]]" . sprintf('%+d', 1 - $left)),
                $this->rightAttribute => new Expression("[[{$this->rightAttribute}]]" . sprintf('%+d', 1 - $left)),
                $this->depthAttribute => new Expression("[[{$this->depthAttribute}]]" . sprintf('%+d', -$depth)),
                $this->treeAttribute => $tree,
            ],
            $this->getDescendants(null, true)->where,
        );
        $this->shift($right + 1, null, $left - $right - 1);
    }

    /**
     * @param int $to
     * @param int $depth
     * @throws Exception
     */
    protected function moveNode(int $to, int $depth = 0): void
    {
        $left = $this->owner->getAttribute($this->leftAttribute);
        $right = $this->owner->getAttribute($this->rightAttribute);
        $depth = $this->owner->getAttribute($this->depthAttribute) - $this->node->getAttribute($this->depthAttribute) - $depth;
        if ($this->treeAttribute === null || $this->owner->getAttribute($this->treeAttribute) === $this->node->getAttribute($this->treeAttribute)) {
            // same root
            $this->owner->updateAll(
                [$this->depthAttribute => new Expression("-[[{$this->depthAttribute}]]" . sprintf('%+d', $depth))],
                $this->getDescendants(null, true)->where,
            );
            $delta = $right - $left + 1;
            if ($left >= $to) {
                $this->shift($to, $left - 1, $delta);
                $delta = $to - $left;
            } else {
                $this->shift($right + 1, $to - 1, -$delta);
                $delta = $to - $right - 1;
            }
            $this->owner->updateAll(
                [
                    $this->leftAttribute => new Expression("[[{$this->leftAttribute}]]" . sprintf('%+d', $delta)),
                    $this->rightAttribute => new Expression("[[{$this->rightAttribute}]]" . sprintf('%+d', $delta)),
                    $this->depthAttribute => new Expression("-[[{$this->depthAttribute}]]"),
                ],
                [
                    'and',
                    $this->getDescendants(null, true)->where,
                    ['<', $this->depthAttribute, 0],
                ],
            );
        } else {
            // move from other root
            $tree = $this->node->getAttribute($this->treeAttribute);
            $this->shift($to, null, $right - $left + 1, $tree);
            $delta = $to - $left;
            $this->owner->updateAll(
                [
                    $this->leftAttribute => new Expression("[[{$this->leftAttribute}]]" . sprintf('%+d', $delta)),
                    $this->rightAttribute => new Expression("[[{$this->rightAttribute}]]" . sprintf('%+d', $delta)),
                    $this->depthAttribute => new Expression("[[{$this->depthAttribute}]]" . sprintf('%+d', -$depth)),
                    $this->treeAttribute => $tree,
                ],
                $this->getDescendants(null, true)->where,
            );
            $this->shift($right + 1, null, $left - $right - 1);
        }
    }
}