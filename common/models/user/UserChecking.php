<?php

namespace common\models\user;

use Yii;

/**
 * This is the model class for table "user_checking".
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $status
 * @property int         $checking_by
 * @property string|null $created_at
 * @property string|null $done_at
 *
 * @property User        $user
 */
class UserChecking extends \common\components\base\ActiveRecord
{
    const STATUS_DONE   = 2;
    const STATUS_CHECKING   = 1;
    const STATUS_WAIT = 0;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_checking';
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
