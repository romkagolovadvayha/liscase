<?php

namespace common\components\queue\clan;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanMemberStatistics;
use common\models\clan\ClanStatistics;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Пересчёт статистики клана для одного игрока (ручной/отладочный сценарий).
 * В продакшене пересчёт кланов делается cron `clan/update-statistics` → {@see UpdateClanStatisticsJob}.
 */
class UpdateSingleClanMemberStatisticsJob extends BaseObject implements JobInterface
{
    /** @var int */
    public $serverId;

    /** @var string|null */
    public $wipe;

    /** @var string */
    public $steamId;

    /**
     * @param \yii\queue\Queue $queue
     */
    public function execute($queue)
    {
        if (!$this->serverId || $this->steamId === null || $this->steamId === '') {
            return;
        }

        $server = Servers::findOne($this->serverId);
        if (!$server) {
            return;
        }

        $wipe = $this->wipe !== null && $this->wipe !== '' ? $this->wipe : $server->currentWipe();

        $user = User::findOne(['steam_id' => (string)$this->steamId]);
        if (!$user) {
            return;
        }

        $member = ClanMember::find()
            ->alias('m')
            ->innerJoin(['c' => Clan::tableName()], '[[c]].[[id]] = [[m]].[[clan_id]]')
            ->where(['m.user_id' => $user->id, 'c.server_id' => $this->serverId])
            ->andWhere(['IS', 'm.leave_date', null])
            ->with('user')
            ->one();

        if (!$member) {
            return;
        }

        try {
            ClanMemberStatistics::updateMemberStatistics($member, $this->serverId, $wipe);
        } catch (\Throwable $e) {
            Yii::error('UpdateSingleClanMemberStatisticsJob member stats: ' . $e->getMessage(), 'clan');
            return;
        }

        $clanStatistics = ClanStatistics::find()
            ->where([
                'clan_id' => $member->clan_id,
                'server_id' => $this->serverId,
                'wipe' => $wipe,
            ])
            ->one();

        if (!$clanStatistics) {
            $clanStatistics = new ClanStatistics();
            $clanStatistics->clan_id = $member->clan_id;
            $clanStatistics->server_id = $this->serverId;
            $clanStatistics->wipe = $wipe;
            if (!$clanStatistics->save(false)) {
                return;
            }
        }

        try {
            $clanStatistics->updateStatistics();
        } catch (\Throwable $e) {
            Yii::error('UpdateSingleClanMemberStatisticsJob clan aggregate: ' . $e->getMessage(), 'clan');
        }
    }
}
