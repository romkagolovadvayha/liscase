<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use common\models\user\UserRaid;
use Yii;

/**
 * This is the model class for table "clan_rankings".
 *
 * @property int $id
 * @property int $clan_id
 * @property int $server_id
 * @property string $ranking_type
 * @property int $position
 * @property float $score
 * @property string $period
 * @property int $calculated_at
 *
 * @property Clan $clan
 * @property Servers $server
 */
class ClanRanking extends ActiveRecord
{
    const RANKING_OVERALL = 'overall';
    const RANKING_KILLS = 'kills';
    const RANKING_REIDER = 'reider';
    const RANKING_FARMER = 'farmer';
    const RANKING_FISHING = 'fishing';
    const RANKING_HUNTER = 'hunter';
    const RANKING_FERMER = 'fermer';
    const RANKING_PLAYTIME = 'playtime';
    const RANKING_ACTIVITY = 'activity';

    const PERIOD_ALL_TIME = 'all_time';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_CURRENT_WIPE = 'current_wipe';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_rankings';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_id', 'server_id', 'ranking_type', 'period', 'calculated_at'], 'required'],
            [['clan_id', 'server_id', 'position', 'calculated_at'], 'integer'],
            [['score'], 'number'],
            [['ranking_type'], 'string', 'max' => 50],
            [['period'], 'string', 'max' => 20],
            [['clan_id', 'server_id', 'ranking_type', 'period'], 'unique', 'targetAttribute' => ['clan_id', 'server_id', 'ranking_type', 'period']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'clan_id' => Yii::t('common', 'Клан'),
            'server_id' => Yii::t('common', 'Сервер'),
            'ranking_type' => Yii::t('common', 'Тип рейтинга'),
            'position' => Yii::t('common', 'Позиция'),
            'score' => Yii::t('common', 'Балл'),
            'period' => Yii::t('common', 'Период'),
            'calculated_at' => Yii::t('common', 'Дата расчета'),
        ];
    }

    /**
     * Gets query for [[Clan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    /**
     * Расчёт рейтингов: сумма {@see UserRaid::score} только по строкам {@see UserRaid::real_raid} = 1.
     * Период задаёт фильтр по вайпу или дате рейда; сохраняется только {@see RANKING_OVERALL}.
     *
     * @param int $serverId
     * @param string $period
     * @return void
     */
    public static function calculateRankings($serverId, $period = self::PERIOD_ALL_TIME)
    {
        $server = Servers::findOne((int)$serverId);
        if (!$server) {
            return;
        }

        static::deleteAll([
            'and',
            ['server_id' => (int)$serverId],
            ['period' => (string)$period],
            ['in', 'ranking_type', static::obsoleteRankingTypes()],
        ]);

        $sumsByClan = static::sumRaidScoresByRaiderClan((int)$serverId, $period, $server);

        $clans = Clan::find()
            ->where(['server_id' => (int)$serverId])
            ->all();

        foreach ($clans as $clan) {
            $score = (float)($sumsByClan[(int)$clan->id] ?? 0.0);
            static::updateClanRanking((int)$clan->id, (int)$serverId, self::RANKING_OVERALL, $period, $score);
        }

        static::updatePositions($serverId, $period);
    }

    /**
     * @return string[]
     */
    private static function obsoleteRankingTypes(): array
    {
        return [
            self::RANKING_KILLS,
            self::RANKING_REIDER,
            self::RANKING_FARMER,
            self::RANKING_FISHING,
            self::RANKING_HUNTER,
            self::RANKING_FERMER,
            self::RANKING_PLAYTIME,
            self::RANKING_ACTIVITY,
        ];
    }

    /**
     * @return array<int, float> clan_id => sum(score)
     */
    private static function sumRaidScoresByRaiderClan(int $serverId, string $period, Servers $server): array
    {
        $q = UserRaid::find()
            ->select([
                'raider_clan_id',
                'SUM(COALESCE([[score]], 0)) AS raid_total',
            ])
            ->where(['server_id' => $serverId])
            ->andWhere(['not', ['raider_clan_id' => null]])
            ->andWhere(['>', 'raider_clan_id', 0]);

        $raidSchema = Yii::$app->db->getTableSchema(UserRaid::tableName(), true);
        if ($raidSchema === null || $raidSchema->getColumn('real_raid') === null) {
            return [];
        }
        $q->andWhere(['real_raid' => 1]);

        switch ($period) {
            case self::PERIOD_CURRENT_WIPE:
                $q->andWhere(['wipe' => (string)$server->currentWipe()]);
                break;
            case self::PERIOD_MONTHLY:
                $q->andWhere(['>=', 'created_at', date('Y-m-01 00:00:00')]);
                break;
            case self::PERIOD_WEEKLY:
                $q->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-7 days'))]);
                break;
            case self::PERIOD_ALL_TIME:
            default:
                break;
        }

        $q->groupBy(['raider_clan_id']);
        $rows = $q->asArray()->all();
        $out = [];
        foreach ($rows as $row) {
            $cid = (int)($row['raider_clan_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $out[$cid] = (float)($row['raid_total'] ?? 0);
        }
        return $out;
    }

    /**
     * Обновление рейтинга клана
     *
     * @param int $clanId
     * @param int $serverId
     * @param string $rankingType
     * @param string $period
     * @param float $score
     * @return void
     */
    public static function updateClanRanking($clanId, $serverId, $rankingType, $period, $score)
    {
        $ranking = static::find()
            ->where([
                'clan_id' => $clanId,
                'server_id' => $serverId,
                'ranking_type' => $rankingType,
                'period' => $period,
            ])
            ->one();

        if (!$ranking) {
            $ranking = new static();
            $ranking->clan_id = $clanId;
            $ranking->server_id = $serverId;
            $ranking->ranking_type = $rankingType;
            $ranking->period = $period;
        }

        $ranking->score = $score;
        $ranking->calculated_at = time();
        $ranking->save(false);
    }

    /**
     * Обновление позиций в рейтинге
     *
     * @param int $serverId
     * @param string $period
     * @return void
     */
    public static function updatePositions($serverId, $period)
    {
        $rankingTypes = [
            self::RANKING_OVERALL,
        ];

        foreach ($rankingTypes as $type) {
            $rankings = static::find()
                ->where(['server_id' => $serverId, 'ranking_type' => $type, 'period' => $period])
                ->orderBy(['score' => SORT_DESC])
                ->all();

            $position = 1;
            foreach ($rankings as $ranking) {
                $ranking->position = $position++;
                $ranking->save(false);
            }
        }
    }

    /**
     * Получение топ кланов
     *
     * @param int $serverId
     * @param string $rankingType
     * @param string $period
     * @param int $limit
     * @return static[]
     */
    public static function getTopClans($serverId, $rankingType, $period, $limit = 10)
    {
        return static::find()
            ->where(['server_id' => $serverId, 'ranking_type' => $rankingType, 'period' => $period])
            ->orderBy(['position' => SORT_ASC])
            ->limit($limit)
            ->all();
    }
}

