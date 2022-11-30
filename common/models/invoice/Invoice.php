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
 * @property int         $type
 * @property string      $amount
 * @property string      $created_at
 *
 * @property User $user
 */
class Invoice extends \common\components\base\ActiveRecord
{
    const TYPE_PAYMENT_BOX         = 1;

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PAYMENT_BOX         => Yii::t('common', 'Оплата контейнера'),
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
            [['user_id', 'box_id', 'type', 'status'], 'integer'],
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
     * @throws \Exception
     */
    public static function createRecord($userId, $amount, $type = null, $boxId = null)
    {
        $model = new Invoice();
        $model->user_id = $userId;
        $model->box_id = $boxId;
        $model->amount = $amount;
        $model->type = Invoice::TYPE_PAYMENT_BOX;
        if (!empty($type)) {
            $model->type = $type;
        }
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return Yii::$app->db->getLastInsertID();
    }
}
