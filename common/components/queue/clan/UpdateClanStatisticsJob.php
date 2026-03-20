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
        if (!$server) {
            return;
        }

        $wipe = $this->wipe ?: $server->currentWipe();

        // Получаем все кланы на сервере
        $clans = Clan::find()
            ->where(['server_id' => $this->serverId])
            ->all();

        foreach ($clans as $clan) {
            try {
                $this->updateClanStatistics($clan, $this->serverId, $wipe);
            } catch (\Exception $e) {
                Yii::error("Error updating clan statistics for clan {$clan->id}: " . $e->getMessage(), 'clan');
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
     * @param Clan $clan
     * @param int $serverId
     * @param string $wipe
     * @return void
     */
    protected function updateClanStatistics($clan, $serverId, $wipe)
    {
        // Получаем или создаем запись статистики клана
        $clanStatistics = ClanStatistics::find()
            ->where([
                'clan_id' => $clan->id,
                'server_id' => $serverId,
                'wipe' => $wipe,
            ])
            ->one();

        if (!$clanStatistics) {
            $clanStatistics = new ClanStatistics();
            $clanStatistics->clan_id = $clan->id;
            $clanStatistics->server_id = $serverId;
            $clanStatistics->wipe = $wipe;
            $clanStatistics->save(false);
        }

        // Обновляем статистику всех участников
        $members = ClanMember::find()
            ->where(['clan_id' => $clan->id])
            ->all();

        foreach ($members as $member) {
            // Обновляем индивидуальную статистику участника
            ClanMemberStatistics::updateMemberStatistics($member, $serverId, $wipe);
        }

        // Обновляем общую статистику клана
        $clanStatistics->updateStatistics();

        // Проверяем и разблокируем достижения
        $clan->checkAchievements();
    }
}

