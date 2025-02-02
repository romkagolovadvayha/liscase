<?php

namespace common\models\invoice;

use Yii;

/**
 * This is the model class for table "payment_bonuses".
 *
 * @property int $id
 * @property int|null $amount
 * @property int|null $bonus
 * @property string|null $created_at
 */
class PaymentBonuses extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'payment_bonuses';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['amount', 'bonus'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'amount' => 'Сумма',
            'bonus' => 'Бонус в процентах',
            'created_at' => 'Дата создания',
        ];
    }
}
