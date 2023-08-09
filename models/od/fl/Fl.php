<?php

namespace app\models\od\fl;

use app\models\od\Source;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 *
 * @property-read Source $source
 */
class Fl extends ActiveRecord
{
    public static function factory($ogrnip): static
    {
        if (!$model = static::findOne(['ogrnip' => $ogrnip])) {
            $model = new static(['ogrnip' => $ogrnip]);
        }

        return $model;
    }

    public static function tableName(): string
    {
        return 'fl';
    }

    public function rules(): array
    {
        return [];
    }

    public function attributeLabels(): array
    {
        return [];
    }

    public function getSource(): ActiveQuery
    {
        return $this->hasOne(Source::class, ['id' => 'source_id']);
    }
}