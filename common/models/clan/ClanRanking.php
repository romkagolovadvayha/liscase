<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
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
     * Расчет рейтингов для всех кланов
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

        $wipe = $server->currentWipe();

        $clans = Clan::find()
            ->innerJoin(
                ['cs' => ClanStatistics::tableName()],
                '[[cs]].[[clan_id]] = [[clans]].[[id]] AND [[cs]].[[server_id]] = :sid AND [[cs]].[[wipe]] = :wipe',
                [':sid' => (int)$serverId, ':wipe' => (string)$wipe]
            )
            ->all();

        foreach ($clans as $clan) {
            // Только статистика по серверу рейтинга ($serverId). getClanStatistics() смотрит на clans.server_id —
            // при переносе клана на другой сервер в clan_rankings для старого сервера попадали чужие top_*.
            $statistics = ClanStatistics::find()
                ->where([
                    'clan_id' => (int)$clan->id,
                    'server_id' => (int)$serverId,
                    'wipe' => (string)$wipe,
                ])
                ->one();
            if ($statistics === null) {
                continue;
            }

            // Расчет рейтингов по различным типам
            $rankingTypes = [
                self::RANKING_KILLS => $statistics->top_kills,
                self::RANKING_REIDER => $statistics->top_reider,
                self::RANKING_FARMER => $statistics->top_farmer,
                self::RANKING_FISHING => $statistics->top_fishing,
                self::RANKING_HUNTER => $statistics->top_hunter,
                self::RANKING_FERMER => $statistics->top_fermer,
                self::RANKING_PLAYTIME => $statistics->top_playtime,
            ];

            // Overall рейтинг - среднее всех рейтингов
            $overallScore = array_sum($rankingTypes) / count($rankingTypes);

            foreach ($rankingTypes as $type => $score) {
                static::updateClanRanking($clan->id, $serverId, $type, $period, $score);
            }

            static::updateClanRanking($clan->id, $serverId, self::RANKING_OVERALL, $period, $overallScore);
        }

        // Обновление позиций после расчета всех баллов
        static::updatePositions($serverId, $period);
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
            self::RANKING_KILLS,
            self::RANKING_REIDER,
            self::RANKING_FARMER,
            self::RANKING_FISHING,
            self::RANKING_HUNTER,
            self::RANKING_FERMER,
            self::RANKING_PLAYTIME,
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

