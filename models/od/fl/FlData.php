<?php

namespace app\models\od\fl;

use app\components\db\ContragentsActiveRecord;

class FlData extends ContragentsActiveRecord
{
    public static function tableName(): array
    {
        return 'fl_data';
    }
}