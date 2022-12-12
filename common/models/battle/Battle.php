<?php

namespace common\models\battle;

use common\models\user\User;
use Yii;
use common\components\base\ActiveRecord;
use yii\base\BaseObject;

/**
 * @property int        $id
 * @property int        $player1_user_id
 * @property int        $player2_user_id
 * @property int        $player_winner_user_id
 * @property int        $status
 * @property int        $auto
 * @property string     $created_at
 *
 * @property User       $player1
 * @property User       $player2
 * @property BattleRate $player1Rate
 * @property BattleRate $player2Rate
 * @property User       $playerWinner
 */
class Battle extends ActiveRecord
{
    const STATUS_REJECT          = 1;
    const STATUS_WAIT_PLAYER     = 2;
    const STATUS_FINISH          = 3;

    /**
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_REJECT       => Yii::t('common', 'Не активный'),
            self::STATUS_WAIT_PLAYER       => Yii::t('common', 'Ожидание игрока'),
            self::STATUS_FINISH       => Yii::t('common', 'Завершен'),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'battle';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'player1_user_id'               => Yii::t('common', 'ID игрока 1'),
            'player2_user_id'               => Yii::t('common', 'ID игрока 2'),
            'player_winner_user_id'               => Yii::t('common', 'ID победителя'),
            'status'              => Yii::t('common', 'Статус'),
            'auto'              => Yii::t('common', 'Создан ботом'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['player1_user_id', 'status', 'created_at'], 'required'],
            [['status', 'player2_user_id', 'player1_user_id', 'auto', 'player_winner_user_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPlayer2(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'player2_user_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPlayer1(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'player1_user_id']);
    }

    /**
     * Gets query for [[BattleRate]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPlayer1Rate(): \yii\db\ActiveQuery
    {
        return $this->hasOne(BattleRate::class, ['battle_id' => 'id', 'user_id' => 'player1_user_id']);
    }

    /**
     * Gets query for [[BattleRate]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPlayer2Rate(): \yii\db\ActiveQuery
    {
        return $this->hasOne(BattleRate::class, ['battle_id' => 'id', 'user_id' => 'player2_user_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPlayerWinner(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'player_winner_user_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPlayerWinnerRate(): \yii\db\ActiveQuery
    {
        return $this->hasOne(BattleRate::class, ['battle_id' => 'id', 'user_id' => 'player_winner_user_id']);
    }

    /**
     * @return int
     */
    public static function createRecord($userId, $auto = 0): int
    {
        $model = new Battle();
        $model->player1_user_id = $userId;
        $model->status = Battle::STATUS_WAIT_PLAYER;
        $model->auto = $auto;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save();
        return Yii::$app->db->getLastInsertID();
    }
}
