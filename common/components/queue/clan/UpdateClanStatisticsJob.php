<?php

namespace common\components\queue\clan;

use common\models\clan\Clan;
use common\models\clan\ClanMember;
use common\models\clan\ClanMemberStatistics;
use common\models\clan\ClanStatistics;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Job для обновления статистики кланов
 */
class UpdateClanStatisticsJob extends BaseObject implements JobInterface
{
    public $serverId;
    public $wipe;

    /**
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        if (!$this->serverId) {
            return;
        }

        $server = Servers::findOne($this->serverId);
        if (!$server || !$server->isClansSystemEnabled()) {
            return;
        }

        $wipe = $this->wipe ?: $server->currentWipe();

        $clanIds = Clan::find()
            ->select('id')
            ->where(['server_id' => (int)$this->serverId])
            ->column();
        if ($clanIds === []) {
            return;
        }

        foreach ($clanIds as $clanId) {
            try {
                $this->updateClanStatistics((int)$clanId, $this->serverId, $wipe);
            } catch (\Exception $e) {
                Yii::error("Error updating clan statistics for clan {$clanId}: " . $e->getMessage(), 'clan');
            }
        }

        // Запускаем расчет рейтингов
        Yii::$app->queueParams->push(new CalculateClanRankingsJob([
            'serverId' => $this->serverId,
        ]));
    }

    /**
     * Обновление статистики клана
     *
     * @param int $clanId
     * @param int $serverId
     * @param string $wipe
     * @return void
     */
    protected function updateClanStatistics($clanId, $serverId, $wipe)
    {
        $clan = Clan::findOne((int)$clanId);
        if ($clan === null) {
            return;
        }

        // Получаем или создаем запись статистики клана
        $clanStatistics = ClanStatistics::find()
            ->where([
                'clan_id' => $clanId,
                'server_id' => $serverId,
                'wipe' => $wipe,
            ])
            ->one();

        if (!$clanStatistics) {
            $clanStatistics = new ClanStatistics();
            $clanStatistics->clan_id = $clanId;
            $clanStatistics->server_id = $serverId;
            $clanStatistics->wipe = $wipe;
            $clanStatistics->save(false);
        }

        // Обновляем статистику всех участников
        $members = ClanMember::find()
            ->where(['clan_id' => $clanId])
            ->all();

        foreach ($members as $member) {
            // Обновляем индивидуальную статистику участника
            ClanMemberStatistics::updateMemberStatistics($member, $serverId, $wipe);
        }

        // Обновляем общую статистику клана
        $clanStatistics->updateStatistics();
        $clan->checkAchievements();

    }
}

