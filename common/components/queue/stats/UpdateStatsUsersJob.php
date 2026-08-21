<?php

namespace common\components\queue\stats;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\db\IntegrityException;
use yii\queue\JobInterface;

/**
 * Пакетно пишет дельты в {@see Statistics}. Пересчёт кланов не делается здесь — только cron
 * `console yii clan/update-statistics` → {@see \common\components\queue\clan\UpdateClanStatisticsJob}.
 */
class UpdateStatsUsersJob extends BaseObject implements JobInterface
{
    /** @var array [steam_id => [key => value, ...], ...] */
    public $users;
    public $serverTag;
    public $serverId;
    public $wipeDate;
    public $batchId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        $transaction = null;
        try {
            $transaction = Yii::$app->db->beginTransaction();
            if ($this->hasAlreadyProcessedBatch()) {
                $transaction->rollBack();
                return;
            }

            $wipeDate = $this->wipeDate;
            $rows = [];
            $cupboardSteamIds = [];

            foreach ($this->users as $steamId => $params) {
                if (empty($params)) {
                    continue;
                }
                unset($params['kills'], $params['deaths']);
                if (!empty($params['cupboard_authorized'])) {
                    $cupboardSteamIds[] = $steamId;
                }
                foreach ($params as $key => $value) {
                    if ($value === '' || $value === null || (is_numeric($value) && (int) $value === 0)) {
                        continue;
                    }
                    $rows[] = [
                        $steamId,
                        $this->serverTag,
                        $key,
                        (int) $value,
                        $wipeDate,
                    ];
                }
            }

            if (empty($rows)) {
                $transaction->commit();
                $this->sendRaidNotifyPromoForSteamIds($cupboardSteamIds);
                return;
            }

            Statistics::batchUpsertIncrementValues($rows);
            $transaction->commit();

            $this->sendRaidNotifyPromoForSteamIds($cupboardSteamIds);
        } catch (\Exception $e) {
            if ($transaction !== null && $transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::$app->telegramChats->sendMessage(
                "UpdateStatsUsersJob::execute: " . $e->getFile() . ':' . $e->getLine() . ':' . $e->getMessage()
            );
            throw $e;
        }
    }

    /**
     * Inserts the receipt in the same DB transaction as the increments. A queue
     * retry therefore either replays the whole transaction or performs no work.
     */
    private function hasAlreadyProcessedBatch(): bool
    {
        $batchId = strtolower(trim((string)$this->batchId));
        if (!preg_match('/^(?:[a-f0-9]{32}|[a-f0-9-]{36})$/', $batchId)) {
            return false;
        }

        try {
            Yii::$app->db->createCommand()->insert('plugin_ingest_receipts', [
                'receipt_key' => 'stats-users:' . $this->serverTag . ':' . $batchId,
                'server_tag' => (string)$this->serverTag,
                'created_at' => time(),
            ])->execute();
        } catch (IntegrityException $e) {
            return true;
        }

        return false;
    }

    private function sendRaidNotifyPromoForSteamIds(array $steamIds): void
    {
        foreach ($steamIds as $steamId) {
            $this->sendRaidNotifyPromo($steamId);
        }
    }

    /**
     * Отправляет уведомление пользователю о подключении Telegram бота
     *
     * @param string $steamId Steam ID пользователя
     * @return void
     */
    protected function sendRaidNotifyPromo($steamId)
    {
        try {
            // Находим пользователя (без создания нового)
            $user = User::find()->where(['steam_id' => $steamId])->one();
            if (!$user) {
                return;
            }

            // Находим сервер
            $server = Servers::findOne(['tag' => $this->serverTag, 'status' => Servers::STATUS_ACTIVE]);
            if (!$server) {
                return;
            }

            // Проверяем нужно ли отправлять уведомление
            // (если бот не подключен или оповещения отключены)
            if (!$user->hasRaidNotifyDeliveryConfigured()) {
                // Отправляем сообщение в игровой чат
                $user->sendRaidNotifyPromoMessage($server);
            }
        } catch (\Exception $e) {
            Yii::error("Failed to send raid notify promo: " . $e->getMessage(), __METHOD__);
        }
    }
}
