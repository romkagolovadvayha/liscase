<?php

namespace common\components\base;

class ActiveRecord extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            \common\components\base\behaviors\TimestampBehavior::class,
        ];
    }
}
