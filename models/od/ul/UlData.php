<?php

namespace app\models\od\ul;

use app\components\db\ContragentsActiveRecord;

class UlData extends ContragentsActiveRecord
{
    public static function tableName(): array
    {
        return 'ul_data';
    }
}