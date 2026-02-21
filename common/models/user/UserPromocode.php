<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property int                 $user_id
 * @property int                 $promocode_id
 * @property string              $created_at
 *
 * @property Promocode[] $promocode
 * @property User $user
 */
class UserPromocode extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'user_promocode';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'promocode_id'], 'required'],
            [['user_id', 'promocode_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[Promocode]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPromocode(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Promocode::class, ['id' => 'promocode_id']);
    }

    /**
     * Создаёт запись об использовании промокода и начисляет бонус.
     * Для одноразовых промокодов сначала атомарно помечает промокод использованным (UPDATE ... WHERE status = ACTIVE),
     * чтобы исключить гонку, когда несколько пользователей успевают пройти проверку до обновления статуса.
     *
     * @param int $userId
     * @param int $promocodeId
     * @return bool true при успехе, false если промокод не найден или одноразовый уже использован другим
     */
    public static function createRecord($userId, $promocodeId): bool
    {
        $promocode = Promocode::findOne($promocodeId);
        if (empty($promocode)) {
            return false;
        }

        // Одноразовый: атомарно «занять» промокод (только один запрос обновит строку)
        if (!empty($promocode->is_single_use)) {
            $affected = Yii::$app->db->createCommand()->update(
                Promocode::tableName(),
                ['status' => Promocode::STATUS_USED],
                [
                    'id' => $promocodeId,
                    'status' => Promocode::STATUS_ACTIVE,
                ]
            )->execute();
            if ($affected === 0) {
                return false; // уже использован или не активен
            }
        }

        $user = User::findOne($userId);
        $model = new UserPromocode();
        $model->user_id = $userId;
        $model->promocode_id = $promocodeId;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);

        $userBalance = $user->getPersonalBalance();
        $profit = new Profit();
        $profit->status = 1;
        $profit->type = Profit::TYPE_PROMOCODE;
        $profit->amount = $promocode->amount;
        $profit->user_balance_id = $userBalance->id;
        $profit->comment = Yii::t('common', 'Активация промокода "{PARAMS_PROMCODE}" на {PARAMS_PROMSUM} RUB', [
            'PARAMS_PROMCODE' => $promocode->code,
            'PARAMS_PROMSUM' => $promocode->amount,
        ], 'ru-RU');
        $profit->created_at = date('Y-m-d H:i:s');
        $profit->save(false);

        return true;
    }
}
