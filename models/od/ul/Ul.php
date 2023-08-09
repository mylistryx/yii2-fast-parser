<?php

namespace app\models\od\ul;

use app\models\od\Source;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 *
 * @property int $id [int]
 * @property int $region_id [int]
 * @property int $source_id [int]
 * @property string $name [varchar(50)]
 * @property string $inn [varchar(50)]
 * @property string $ogrn [varchar(50)]
 * @property string $kpp [varchar(50)]
 * @property string $date [datetime]
 * @property string $created_at [datetime]
 * @property string $updated_at [datetime]
 *
 * @property-read Source $source
 */
class Ul extends ActiveRecord
{
    public static function factory($ogrn, $inn, $kpp)
    {
        if (!$model = static::findOne(['ogrn' => $ogrn, 'inn' => $inn, 'kpp' => $kpp])) {
            $model = new static(['ogrn' => $ogrn, 'inn' => $inn, 'kpp' => $kpp]);
        }
        return $model;
    }

    public static function tableName(): string
    {
        return 'ul';
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