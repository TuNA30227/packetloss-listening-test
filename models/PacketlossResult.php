<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class PacketlossResult extends ActiveRecord
{
    public static function tableName()
    {
        return 'packetloss_result';
    }

    public function rules()
    {
        return [
            [['sample_id', 'score', 'user_ip', 'user_name'], 'required'],
            [['score'], 'integer', 'min' => 1, 'max' => 5],
            [['submitted_at'], 'safe'],
        ];
    }
    
}
