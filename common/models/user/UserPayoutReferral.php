<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property int    $user_id
 * @property float  $amount
 * @property string $created_at
 *
 * @property User $user
 */
class UserPayoutReferral extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'user_payout_referral';
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
}
