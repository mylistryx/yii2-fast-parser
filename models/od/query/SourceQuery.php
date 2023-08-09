<?php

namespace app\models\od\query;

use app\components\tree\AdjacencyListQueryTrait;
use app\models\od\Source;
use yii\db\ActiveQuery;

/**
 * @see Source
 */
class SourceQuery extends ActiveQuery
{
    use AdjacencyListQueryTrait;

    public function directory(): static
    {
        return $this->andWhere(['mime' => null]);
    }

    public function zip(): static
    {
        return $this->andWhere(['mime' => Source::TYPE_ZIP]);
    }

    public function notParsed(): static
    {
        return $this->andWhere(['parsed_at' => null]);
    }

    public function notStarted(): static
    {
        return $this->andWhere(['started_at' => null]);
    }
}