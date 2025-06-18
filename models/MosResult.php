<?php
namespace app\models;

use yii\db\ActiveRecord;

class MosResult extends ActiveRecord
{
    public static function tableName()
    {
        return 'mos_result';
    }
}
