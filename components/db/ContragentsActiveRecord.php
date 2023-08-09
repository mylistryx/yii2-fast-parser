<?php

namespace app\components\db;

use Yii;
use yii\db\ActiveRecord;

abstract class ContragentsActiveRecord extends ActiveRecord
{
    //public static function getDb()
    //{
//        return Yii::$app->dbContragents;
    //}

    /**
     * DEV!
     * @param array $data
     * @return bool
     */
    public function tryToSave(array $data): bool
    {
        if (!$this->save()) {
            var_dump($this->errors, $data);
            die();
        }

        return true;
    }
}