<?php

namespace common\components\queue\stats;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class UpdateStatsUsersJob extends BaseObject implements JobInterface
{
    /** @var array [steam_id => [key => value, ...], ...] */
    public $users;
    public $serverTag;
    public $serverId;
    public $wipeDate;

    /** Размер пачки для batch upsert (снижает нагрузку на БД и размер запроса) */
    private const BATCH_SIZE = 500;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
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
                $this->sendRaidNotifyPromoForSteamIds($cupboardSteamIds);
                return;
            }

            $db = Yii::$app->db;
            $tableName = $db->schema->getRawTableName(Statistics::tableName());
            $isMysql = ($db->driverName === 'mysql');

            if ($isMysql) {
                $this->executeMysqlBatchUpsert($tableName, $rows);
            } else {
                $this->executeFallbackPerUser($rows, $cupboardSteamIds, $wipeDate);
                return;
            }

            $this->sendRaidNotifyPromoForSteamIds($cupboardSteamIds);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage(
                "UpdateStatsUsersJob::execute: " . $e->getFile() . ':' . $e->getLine() . ':' . $e->getMessage()
            );
        }
    }

    /**
     * Пакетный upsert для MySQL: один запрос на пачку строк вместо N SELECT + M save на пользователя.
     */
    private function executeMysqlBatchUpsert(string $tableName, array $rows): void
    {
        $chunks = array_chunk($rows, self::BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $placeholders = [];
            $params = [];
            $i = 0;
            foreach ($chunk as $row) {
                $placeholders[] = "(:s{$i}, :t{$i}, :k{$i}, :v{$i}, :w{$i})";
                $params[":s{$i}"] = $row[0];
                $params[":t{$i}"] = $row[1];
                $params[":k{$i}"] = $row[2];
                $params[":v{$i}"] = $row[3];
                $params[":w{$i}"] = $row[4];
                $i++;
            }
            $sql = "INSERT INTO {$tableName} (steam_id, server_tag, `key`, value, wipe)\nVALUES "
                . implode(",\n", $placeholders)
                . "\nON DUPLICATE KEY UPDATE value = value + VALUES(value)";
            Yii::$app->db->createCommand($sql)->bindValues($params)->execute();
        }
    }

    /**
     * Fallback для не-MySQL: по одному пользователю (без массового SELECT по всем ключам).
     */
    private function executeFallbackPerUser(array $rows, array $cupboardSteamIds, string $wipeDate): void
    {
        $bySteam = [];
        foreach ($rows as $row) {
            $bySteam[$row[0]][$row[2]] = ($bySteam[$row[0]][$row[2]] ?? 0) + $row[3];
        }
        foreach ($bySteam as $steamId => $params) {
            $statistics = Statistics::find()
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['server_tag' => $this->serverTag])
                ->andWhere(['wipe' => $wipeDate])
                ->indexBy('key')
                ->all();
            $transaction = Yii::$app->db->beginTransaction();
            try {
                foreach ($params as $key => $value) {
                    if (!empty($statistics[$key])) {
                        $statistics[$key]->value += $value;
                        $statistics[$key]->save(false);
                    } else {
                        $m = new Statistics();
                        $m->steam_id = $steamId;
                        $m->server_tag = $this->serverTag;
                        $m->key = $key;
                        $m->value = $value;
                        $m->wipe = $wipeDate;
                        $m->save(false);
                    }
                }
                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
        $this->sendRaidNotifyPromoForSteamIds($cupboardSteamIds);
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
            if (empty($user->telegram_chat_id) || $user->is_telegram_blocked || !$user->raid_notify) {
                // Отправляем сообщение в игровой чат
                $user->sendRaidNotifyPromoMessage($server);
            }
        } catch (\Exception $e) {
            Yii::error("Failed to send raid notify promo: " . $e->getMessage(), __METHOD__);
        }
    }
}