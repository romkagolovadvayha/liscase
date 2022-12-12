<?php

namespace common\models\battle;

use common\models\user\User;
use common\models\user\UserDrop;
use Yii;
use common\components\base\ActiveRecord;
use yii\base\BaseObject;

/**
 * @property int      $id
 * @property int      $battle_id
 * @property int      $user_id
 * @property int      $user_drop_id
 * @property string   $created_at
 *
 * @property Battle   $battle
 * @property User     $user
 * @property UserDrop $userDrop
 */
class BattleRate extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'battle_rate';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'battle_id'               => Yii::t('common', 'ID сражения'),
            'user_id'               => Yii::t('common', 'ID игрока'),
            'user_drop_id'               => Yii::t('common', 'ID ставки'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['battle_id', 'user_id', 'user_drop_id', 'created_at'], 'required'],
            [['battle_id', 'user_id', 'user_drop_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBattle(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Battle::class, ['id' => 'battle_id']);
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
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserDrop(): \yii\db\ActiveQuery
    {
        return $this->hasOne(UserDrop::class, ['id' => 'user_drop_id']);
    }

    /**
     * @param $userId
     * @param $battleId
     *
     * @return int
     */
    public static function createRecord($userId, $battleId, $userDropId): int
    {
        $model = new BattleRate();
        $model->user_id = $userId;
        $model->battle_id = $battleId;
        $model->user_drop_id = $userDropId;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save();
        return Yii::$app->db->getLastInsertID();
    }
}
