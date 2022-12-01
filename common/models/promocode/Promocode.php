<?php

namespace common\models\promocode;

use common\components\web\Cookie;
use common\models\user\UserPromocode;
use Yii;
use common\models\user\User;
use yii\base\BaseObject;

/**
 * This is the model class for table "promocode".
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $code
 * @property int    $type
 * @property int    $status
 * @property string $percent
 * @property int    $left_count
 * @property int    $count
 * @property string $created_at
 *
 * @property User   $user
 */
class Promocode extends \common\components\base\ActiveRecord
{
    const TYPE_PAYMENT_DISCOUNT  = 1;
    const TYPE_PAYMENT_PAYMENT   = 2;

    const STATUS_ACTIVE  = 1;
    const STATUS_USED    = 2;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_ACTIVE      => Yii::t('common', 'Активен'),
            self::STATUS_USED       => Yii::t('common', 'Использован'),
        ];
    }

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PAYMENT_DISCOUNT      => Yii::t('common', 'Скидка'),
            self::TYPE_PAYMENT_PAYMENT       => Yii::t('common', 'К пополнению'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'promocode';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'type', 'percent', 'code'], 'required'],
            [['user_id', 'type', 'status', 'percent', 'count', 'left_count'], 'integer'],
            [['percent'], 'number', 'min' => 1],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'user_id'       => Yii::t('common', 'ID пользователя'),
            'code'       => Yii::t('common', 'Промокод'),
            'type'          => Yii::t('common', 'Тип'),
            'percent'       => Yii::t('common', 'Процент'),
            'status'        => Yii::t('common', 'Статус'),
            'count'         => Yii::t('common', 'Количество'),
            'left_count'          => Yii::t('common', 'Осталось'),
            'created_at'    => Yii::t('common', 'Дата операции'),
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
     * @param       $userId
     * @param       $count
     * @param       $code
     * @param       $type
     * @param false $percent
     * @param null  $status
     *
     * @return string
     */
    public static function createRecord($userId, $code, $count, $type, $percent = false, $status = null)
    {
        $model = new Promocode();
        $model->user_id = $userId;
        $model->percent = $percent;
        $model->type = $type;
        $model->code = $code;
        $model->count = $count;
        $model->left_count = $count;
        $model->status = self::STATUS_ACTIVE;
        if (empty($status)) {
            $model->status = $status;
        }
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return Yii::$app->db->getLastInsertID();
    }

    /**
     * @param $code
     *
     * @return Promocode|\yii\db\ActiveRecord|null
     */
    public static function findByCode($code)
    {
        return Promocode::find()
                        ->cache(3)
                        ->andWhere(['code' => $code])
                        ->one();
    }

    /**
     *
     * @return Promocode|\yii\db\ActiveRecord|null
     */
    public static function getActivePromocode() {
        $code = Cookie::getValue('promocode');
        if (empty($code)) {
            return null;
        }
        $model = Promocode::findByCode($code);
        if (empty($model)) {
            Cookie::remove('promocode');
            return null;
        }
        if ($model->left_count <= 0) {
            Cookie::remove('promocode');
            return null;
        }
        if (!Yii::$app->user->isGuest) {
            $user =  Yii::$app->user->identity;
            $exist = UserPromocode::find()
                                  ->andWhere(['user_id' => $user->id])
                                  ->andWhere(['promocode_id' => $model->id])
                                  ->exists();
            if ($exist) {
                Cookie::remove('promocode');
                return null;
            }
        }
        return $model;
    }
}
