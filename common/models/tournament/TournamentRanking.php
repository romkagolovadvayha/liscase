<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;
use common\models\clan\Clan;
use Yii;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $clan_id
 * @property float $score
 * @property int $position
 * @property int $calculated_at
 *
 * @property Tournament $tournament
 * @property Clan $clan
 */
class TournamentRanking extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tournament_rankings';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['tournament_id', 'clan_id', 'calculated_at'], 'required'],
            [['tournament_id', 'clan_id', 'position', 'calculated_at'], 'integer'],
            [['score'], 'number'],
            [['tournament_id', 'clan_id'], 'unique', 'targetAttribute' => ['tournament_id', 'clan_id']],
            [['tournament_id'], 'exist', 'targetClass' => Tournament::class, 'targetAttribute' => ['tournament_id' => 'id']],
            [['clan_id'], 'exist', 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
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

    /**
     * @return static[]
     */
    public static function getTopForTournament(int $tournamentId, int $limit = 10): array
    {
        return static::find()
            ->where(['tournament_id' => $tournamentId])
            ->andWhere(['>', 'position', 0])
            ->orderBy(['position' => SORT_ASC])
            ->limit($limit)
            ->with('clan')
            ->all();
    }
}
