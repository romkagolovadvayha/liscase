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
    const TYPE_PROMOCODE             = 4;
    public const TYPE_DAILY_REWARD_LIST = 5;
    public const TYPE_ACHIEVEMENT = 6;
    public const TYPE_DAILY_REWARD_LIST_BOX_SMALL = 7;
    public const TYPE_DAILY_REWARD_LIST_BOX_BIG = 8;
    public const TYPE_TASK = 9;

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_REFERRAL         => Yii::t('common', 'Партнерская программа'),
            self::TYPE_BONUS            => Yii::t('common', 'Бонус'),
            self::TYPE_SELL_DROP            => Yii::t('common', 'Продажа предметов'),
            self::TYPE_PROMOCODE            => Yii::t('common', 'Промокод'),
            self::TYPE_DAILY_REWARD_LIST => Yii::t('common', 'Ежедневная награда'),
            self::TYPE_DAILY_REWARD_LIST_BOX_SMALL => Yii::t('common', 'Ежедневная награда малый бокс'),
            self::TYPE_DAILY_REWARD_LIST_BOX_BIG => Yii::t('common', 'Ежедневная награда большой бокс'),
            self::TYPE_ACHIEVEMENT => Yii::t('common', 'Достижения'),
            self::TYPE_TASK => Yii::t('common', 'Выполнение задания'),
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
