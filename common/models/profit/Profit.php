<?php

namespace common\models\profit;

use Yii;
use yii\helpers\ArrayHelper;
use common\models\user\User;
use common\models\user\UserBalance;

/**
 * This is the model class for table "profit".
 *
 * @property int         $id
 * @property int         $user_balance_id
 * @property int         $type
 * @property string      $amount
 * @property string      $comment
 * @property int         $status
 * @property string      $created_at
 *
 * @property UserBalance $userBalance
 */
class Profit extends \common\components\base\ActiveRecord
{
    const TYPE_REFERRAL         = 1;
    const TYPE_BONUS            = 2;
    const TYPE_SELL_DROP             = 3;

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_REFERRAL         => Yii::t('common', 'Партнерская программа'),
            self::TYPE_BONUS            => Yii::t('common', 'Бонус'),
            self::TYPE_SELL_DROP            => Yii::t('common', 'Продажа предметов'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'profit';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_balance_id', 'type', 'amount'], 'required'],
            [['user_balance_id', 'type', 'status'], 'integer'],
            [['amount'], 'number', 'min' => 0.01],
            [['created_at'], 'safe'],
            [['comment'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'                => 'ID',
            'user_balance_id'   => Yii::t('common', 'Баланс'),
            'type'              => Yii::t('common', 'Тип'),
            'amount'            => Yii::t('common', 'Сумма'),
            'status'            => Yii::t('common', 'Статус'),
            'comment'           => Yii::t('common', 'Комментарий'),
            'created_at'        => Yii::t('common', 'Дата операции'),
        ];
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        $this->userBalance->recalculateBalance();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserBalance()
    {
        return $this->hasOne(UserBalance::class, ['id' => 'user_balance_id']);
    }

    /**
     * @return string|null
     */
    public function getCurrencyLabel()
    {
        return ArrayHelper::getValue(UserBalance::getCurrencyLabelList(), $this->userBalance->type);
    }
}
