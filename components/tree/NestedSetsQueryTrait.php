<?php

namespace app\components\tree;

use yii\db\ActiveQuery;

trait NestedSetsQueryTrait
{
    /**
     * @return ActiveQuery
     */
    public function roots(): ActiveQuery
    {
        $class = $this->modelClass;
        $model = new $class;
        return $this->andWhere([$model->leftAttribute => 1]);
    }
}