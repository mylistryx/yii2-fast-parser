<?php

namespace app\components\tree;

use yii\db\ActiveQuery;

trait AdjacencyListQueryTrait
{
    /**
     * @return ActiveQuery
     */
    public function roots(): ActiveQuery
    {
        /** @var ActiveQuery $this */
        $class = $this->modelClass;
        $model = new $class;
        return $this->andWhere([$model->parentAttribute => null]);
    }
}