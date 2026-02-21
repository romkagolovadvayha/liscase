<?php

namespace common\models\promocode;

use common\components\web\Cookie;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "promocode".
 *
 * @property int    $id
 * @property string $code
 * @property int    $type
 * @property int    $status
 * @property int    $is_single_use
 * @property string $amount
 * @property string|null $finished_at
 * @property string $created_at
 *
 * @property UserPromocode[] $userPromocodes
 */
class Promocode extends \common\components\base\ActiveRecord
{
    const TYPE_PAYMENT_DISCOUNT  = 1;
    const TYPE_PAYMENT_PAYMENT   = 2;

    const STATUS_NOT_ACTIVE    = 0;
    const STATUS_ACTIVE  = 1;
    const STATUS_USED    = 2;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_NOT_ACTIVE      => Yii::t('common', 'Не активен'),
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
            [['type', 'amount', 'code'], 'required'],
            [['type', 'status', 'amount', 'is_single_use'], 'integer'],
            [['created_at', 'finished_at'], 'safe'],
            [['finished_at'], 'default', 'value' => null],
            [['finished_at'], 'filter', 'filter' => function ($v) { return $v === '' ? null : $v; }],
            [['is_single_use'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'code'          => Yii::t('common', 'Промокод'),
            'type'          => Yii::t('common', 'Тип'),
            'status'        => Yii::t('common', 'Статус'),
            'is_single_use' => Yii::t('common', 'Одноразовый'),
            'amount'        => Yii::t('common', 'Сумма'),
            'finished_at'   => Yii::t('common', 'Дата завершения'),
            'created_at'    => Yii::t('common', 'Дата начала'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserPromocodes()
    {
        return $this->hasMany(UserPromocode::class, ['promocode_id' => 'id']);
    }

    /**
     * Пользователь, который ввёл промокод (для одноразовых — один).
     * @return \common\models\user\User|null
     */
    public function getUsedByUser()
    {
        $up = $this->getUserPromocodes()->with('user')->limit(1)->one();
        return $up ? $up->user : null;
    }

    /**
     * Текст для колонки «Кто ввёл»: один пользователь — его steam_id, несколько — число.
     * @return string
     */
    public function getUsedByDisplay()
    {
        $count = (int) $this->getUserPromocodes()->count();
        if ($count === 0) {
            return '—';
        }
        if ($count === 1) {
            $user = $this->getUsedByUser();
            return $user ? ($user->steam_id ?? (string) $user->id) : '—';
        }
        return (string) $count;
    }

    /**
     * @param      $code
     * @param      $amount
     * @param null $finishedAt
     *
     * @return string
     */
    public static function createRecord($code, $amount, $finishedAt = null)
    {
        $model = new Promocode();
        $model->amount = $amount;
        $model->type = self::TYPE_PAYMENT_PAYMENT;
        $model->code = $code;
        $model->finished_at = $finishedAt;
        $model->status = self::STATUS_ACTIVE;
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
                        ->andWhere(['code' => $code])
                        ->one();
    }

    /**
     * Генерирует уникальный код для одноразового промокода (A-Z, 0-9, длина 12).
     * @return string
     */
    public static function generateUniqueCode()
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (static::findByCode($code) !== null);

        return $code;
    }

}
