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
        if (!$server || !$server->isClansSystemEnabled()) {
            return;
        }

        $wipe = $this->wipe !== null && $this->wipe !== '' ? $this->wipe : $server->currentWipe();

        $user = User::findOne(['steam_id' => (string)$this->steamId]);
        if (!$user) {
            return;
        }

        $members = ClanMember::find()
            ->alias('m')
            ->innerJoin(['c' => Clan::tableName()], '[[c]].[[id]] = [[m]].[[clan_id]]')
            ->where(['m.user_id' => $user->id])
            ->andWhere(['IS', 'm.leave_date', null])
            ->with('user')
            ->all();

        if ($members === []) {
            return;
        }

        foreach ($members as $member) {
            try {
                ClanMemberStatistics::updateMemberStatistics($member, $this->serverId, $wipe);
            } catch (\Throwable $e) {
                Yii::error('UpdateSingleClanMemberStatisticsJob member stats: ' . $e->getMessage(), 'clan');
                continue;
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
                    continue;
                }
            }

            try {
                $clanStatistics->updateStatistics();
            } catch (\Throwable $e) {
                Yii::error('UpdateSingleClanMemberStatisticsJob clan aggregate: ' . $e->getMessage(), 'clan');
            }
        }
    }
}
