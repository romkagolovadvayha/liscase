<?php

namespace common\models\invoice;

use Yii;

/**
 * This is the model class for table "deposit_bonus".
 *
 * @property int $id
 * @property int $bonus
 * @property int $min_amount
 */
class DepositBonus extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'deposit_bonus';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['bonus', 'min_amount'], 'required'],
            [['bonus', 'min_amount'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'bonus' => 'Бонус, %',
            'min_amount' => 'Минимальная сумма пополнения',
        ];
    }
}
