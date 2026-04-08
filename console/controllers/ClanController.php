<?php

namespace console\controllers;

use common\components\clan\ClanActiveMembersCache;
use common\components\queue\clan\UpdateClanStatisticsJob;
use common\models\servers\Servers;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Кланы: кэш активных участников и полный пересчёт статистики/рейтингов (cron).
 */
class ClanController extends Controller
{
    /**
     * Обновить кэш steam_id участников кланов (онлайн за последние N дней) по всем серверам.
     * Рекомендуется cron: каждые 5 минут.
     *
     * @param int|null $serverId только один сервер (опционально)
     */
    public function actionRefreshActiveMembersCache($serverId = null)
    {
        if ($serverId !== null && $serverId !== '') {
            ClanActiveMembersCache::refreshServer((int)$serverId);
            $this->stdout("Cache refreshed for server {$serverId}\n");
            return ExitCode::OK;
        }

        ClanActiveMembersCache::refreshAll();
        $this->stdout("Cache refreshed for all servers\n");

        return ExitCode::OK;
    }

    /**
     * Полный пересчёт статистики кланов на сервере(ах) и рейтингов (через очередь {@see UpdateClanStatisticsJob}).
     * Основной путь обновления кланов после записи в `statistics` — этот action по cron (см. `console.php` → clanUpdateStatistics).
     *
     * @param int|null $serverId только один сервер (опционально)
     */
    public function actionUpdateStatistics($serverId = null)
    {
        $query = Servers::find()
            ->where(['status' => Servers::STATUS_ACTIVE]);
        if (Servers::hasClansEnabledColumn()) {
            $query->andWhere(['clans_enabled' => 1]);
        }
        if ($serverId !== null && $serverId !== '') {
            $query->andWhere(['id' => (int)$serverId]);
        }

        $servers = $query->all();
        foreach ($servers as $server) {
            $wipe = $server->currentWipe();
            try {
                Yii::$app->queueParams->push(new UpdateClanStatisticsJob([
                    'serverId' => $server->id,
                    'wipe' => $wipe,
                ]));
                $this->stdout("Queued UpdateClanStatisticsJob for server {$server->id} ({$server->tag})\n");
            } catch (\Throwable $e) {
                Yii::error('ClanController::actionUpdateStatistics: ' . $e->getMessage(), 'clan');
                $this->stderr("Error queueing server {$server->id}: {$e->getMessage()}\n");
            }
        }

        return ExitCode::OK;
    }
}
