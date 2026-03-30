<?php

namespace common\models\invoice;

use Yii;
use common\models\user\User;
use yii\base\BaseObject;

/**
 * This is the model class for table "invoice".
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $box_id
 * @property int         $sets_id
 * @property int         $drop_id
 * @property int         $type
 * @property string      $comment
 * @property string      $amount
 * @property string      $created_at
 *
 * @property User $user
 */
class Invoice extends \common\components\base\ActiveRecord
{
    const TYPE_PAYMENT_BOX         = 1;
    const TYPE_PAYMENT_MARKET_DROP      = 2;
    const TYPE_PAYMENT_RADIO_FIRST      = 3;
    /** Списание при ручном переводе баланса между пользователями (админка) */
    public const TYPE_ADMIN_TRANSFER = 4;

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PAYMENT_BOX         => Yii::t('common', 'Оплата контейнера'),
            self::TYPE_PAYMENT_MARKET_DROP         => Yii::t('common', 'Оплата предмета на маркете'),
            self::TYPE_PAYMENT_RADIO_FIRST         => Yii::t('common', 'Оплата постановки трека первым в очередь'),
            self::TYPE_ADMIN_TRANSFER => Yii::t('common', 'Перевод баланса (админ.)'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'invoice';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'type', 'amount'], 'required'],
            [['user_id', 'box_id', 'type'], 'integer'],
            [['amount'], 'number', 'min' => 0.01],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'                => 'ID',
            'user_id'   => Yii::t('common', 'ID пользователя'),
            'box_id'   => Yii::t('common', 'ID контейнера'),
            'type'              => Yii::t('common', 'Тип'),
            'amount'            => Yii::t('common', 'Сумма'),
            'created_at'        => Yii::t('common', 'Дата операции'),
        ];
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        $this->user->getPersonalBalance()->recalculateBalance();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @param      $userId
     * @param      $amount
     * @param null $type
     * @param null $boxId
     *
     * @return string
     */
    public static function createRecord($userId, $amount, $type = null, $boxId = null, $setsId = null, $dropId = null, $comment = "")
    {
        $model = new Invoice();
        $model->user_id = $userId;
        $model->box_id = $boxId;
        $model->sets_id = $setsId;
        $model->drop_id = $dropId;
        $model->amount = $amount;
        $model->comment = $comment;
        $model->type = Invoice::TYPE_PAYMENT_BOX;
        if (!empty($type)) {
            $model->type = $type;
        }
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return Yii::$app->db->getLastInsertID();
    }
}
