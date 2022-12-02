<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
use common\models\promocode\Promocode;
use Yii;

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
     * @param $userId
     * @param $promocodeId
     *
     * @return bool
     */
    public static function createRecord($userId, $promocodeId): bool
    {
        $promocode = Promocode::findOne($promocodeId);
        if (empty($promocode)) {
            return false;
        }
        $promocode->left_count--;
        $promocode->save(false);

        $model = new UserPromocode();
        $model->user_id = $userId;
        $model->promocode_id = $promocodeId;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return true;
    }
}
