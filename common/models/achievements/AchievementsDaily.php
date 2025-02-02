<?php

namespace common\models\achievements;

use common\models\box\Drop;
use Yii;

/**
 * This is the model class for table "achievements_daily".
 *
 * @property int $id
 * @property int $daily
 * @property int $drop_id
 * @property int $amount
 *
 * @property Drop $drop
 */
class AchievementsDaily extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'achievements_daily';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['daily', 'drop_id', 'amount'], 'required'],
            [['daily', 'drop_id', 'amount'], 'integer'],
            [['drop_id'], 'exist', 'skipOnError' => true, 'targetClass' => Drop::class, 'targetAttribute' => ['drop_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'daily' => 'День',
            'drop_id' => 'Подарок',
            'amount' => 'Количество',
        ];
    }

    /**
     * Gets query for [[Drop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDrop()
    {
        return $this->hasOne(Drop::class, ['id' => 'drop_id']);
    }
}
