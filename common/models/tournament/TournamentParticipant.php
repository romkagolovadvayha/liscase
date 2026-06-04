<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * @property int $id
 * @property int $registration_id
 * @property int $user_id
 * @property string $added_at
 * @property int $created_at
 *
 * @property TournamentRegistration $registration
 * @property User $user
 */
class TournamentParticipant extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tournament_participants';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['registration_id', 'user_id', 'added_at'], 'required'],
            [['registration_id', 'user_id'], 'integer'],
            [['added_at'], 'safe'],
            [['registration_id', 'user_id'], 'unique', 'targetAttribute' => ['registration_id', 'user_id']],
            [['registration_id'], 'exist', 'targetClass' => TournamentRegistration::class, 'targetAttribute' => ['registration_id' => 'id']],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function getRegistration()
    {
        return $this->hasOne(TournamentRegistration::class, ['id' => 'registration_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
