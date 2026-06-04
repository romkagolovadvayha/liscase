<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;
use common\models\clan\Clan;
use common\models\user\User;
use Yii;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $clan_id
 * @property int $registered_by_user_id
 * @property string $registered_at
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Tournament $tournament
 * @property Clan $clan
 * @property User $registeredByUser
 * @property TournamentParticipant[] $participants
 */
class TournamentRegistration extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tournament_registrations';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tournament_id', 'clan_id', 'registered_by_user_id', 'registered_at'], 'required'],
            [['tournament_id', 'clan_id', 'registered_by_user_id'], 'integer'],
            [['registered_at'], 'safe'],
            [['tournament_id', 'clan_id'], 'unique', 'targetAttribute' => ['tournament_id', 'clan_id']],
            [['tournament_id'], 'exist', 'targetClass' => Tournament::class, 'targetAttribute' => ['tournament_id' => 'id']],
            [['clan_id'], 'exist', 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['registered_by_user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['registered_by_user_id' => 'id']],
        ];
    }

    public function getTournament()
    {
        return $this->hasOne(Tournament::class, ['id' => 'tournament_id']);
    }

    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    public function getRegisteredByUser()
    {
        return $this->hasOne(User::class, ['id' => 'registered_by_user_id']);
    }

    public function getParticipants()
    {
        return $this->hasMany(TournamentParticipant::class, ['registration_id' => 'id']);
    }

    public function getParticipantUserIds(): array
    {
        return array_map('intval', TournamentParticipant::find()
            ->select('user_id')
            ->where(['registration_id' => (int)$this->id])
            ->column());
    }

    public function getParticipantCount(): int
    {
        return (int)TournamentParticipant::find()
            ->where(['registration_id' => (int)$this->id])
            ->count();
    }
}
