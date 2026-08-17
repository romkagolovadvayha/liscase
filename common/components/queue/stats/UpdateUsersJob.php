<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\components\queue\process\UserSteamInfoUpdateJob;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\team\Team;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use yii\base\BaseObject;
use yii\helpers\HtmlPurifier;
use yii\queue\JobInterface;

class UpdateUsersJob extends BaseObject implements JobInterface
{
    private const STEAM_REFRESH_COOLDOWN = 6 * 60 * 60;
    private const USER_UPDATE_BATCH_SIZE = 250;

    public $data;
    public $serverTag;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $request = json_decode($this->data, 1);
            /** @var Servers $server */
            $server = Servers::find()
                             ->cache(60)
                             ->andWhere(['tag' => $this->serverTag])
                             ->one();
            if ($server === null) {
                return;
            }
            $incoming = $this->normalizeUsers(isset($request['users']) && is_array($request['users']) ? $request['users'] : []);
            if ($incoming === []) {
                return;
            }

            $playTimeCount = 5;
            $wipe = $server->currentWipe();
            $playtimeRows = [];
            $steamIds = array_keys($incoming);
            /** @var User[] $knownUsers */
            $knownUsers = User::find()
                ->andWhere(['steam_id' => $steamIds])
                ->indexBy('steam_id')
                ->all();
            $bulkRows = [];
            $now = date('Y-m-d H:i:s');

            foreach ($incoming as $steamId => $item) {
                try {
                    $user = $knownUsers[$steamId] ?? User::findBySteamId($steamId, false, 'update user');
                    if (empty($user)) {
                        Yii::$app->telegramChats->sendMessage("UpdateUsersJob: user empty " . $steamId);
                        continue;
                    }

                    $updatedAt = strtotime((string) $user->updated_at);
                    $refreshKey = 'update_users_steam_refresh_v1_' . $steamId;
                    if (($updatedAt === false || $updatedAt < time() - 24 * 60 * 60)
                        && Yii::$app->cache->add($refreshKey, 1, self::STEAM_REFRESH_COOLDOWN)) {
                        \Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $user->steam_id]));
                    }

                    $sanitized = [
                        'steam_id' => $steamId,
                        'username' => HtmlPurifier::process((string) $item['username']),
                        'ip' => (string) $item['ip'],
                        'ping' => (int) $item['ping'],
                    ];
                    if ((int) $user->server_id !== (int) $server->id) {
                        // Preserve User::afterSave(): a real server switch can
                        // enqueue a Discord role refresh.
                        $user->username = $sanitized['username'];
                        $user->ip = $sanitized['ip'];
                        $user->ping = $sanitized['ping'];
                        $user->server_id = (int) $server->id;
                        $user->last_visit_server_at = $now;
                        $user->save(false, ['username', 'ip', 'ping', 'server_id', 'last_visit_server_at']);
                    } else {
                        $bulkRows[] = $sanitized;
                    }
                    $playtimeRows[] = [$steamId, $this->serverTag, 'playtime', $playTimeCount, $wipe];
                } catch (\Exception $e) {
                    Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }
            }

            $this->batchUpdateOnlineUsers($bulkRows, (int) $server->id, $now);
            Statistics::batchUpsertIncrementValues($playtimeRows);
            // A cheap, reusable list for consumers that need to work only
            // with players who are actually online. It is deliberately not a
            // prebuilt snapshot: balance/support must remain request-fresh.
            Yii::$app->cache->set(
                'rust_menu_online_players_v1_' . (int) $server->id,
                array_values(array_unique(array_column($playtimeRows, 0))),
                10 * 60
            );
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }

    /**
     * Last record wins when a malformed upload contains a duplicate Steam ID.
     * This also bounds all following DB work to unique players.
     */
    private function normalizeUsers(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $steamId = trim((string) ($item['steam_id'] ?? ''));
            if (!preg_match('/^\d{17}$/', $steamId)) {
                continue;
            }
            $result[$steamId] = [
                'username' => (string) ($item['username'] ?? $steamId),
                'ip' => (string) ($item['ip'] ?? ''),
                'ping' => (int) ($item['ping'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Online uploads arrive every few minutes and normally keep users on the
     * same server. Updating those rows with one CASE query avoids N writes and
     * ActiveRecord setup while retaining the individual save path for actual
     * server changes.
     */
    private function batchUpdateOnlineUsers(array $rows, int $serverId, string $now): void
    {
        if ($rows === []) {
            return;
        }

        $db = Yii::$app->db;
        $table = $db->quoteTableName(User::tableName());
        foreach (array_chunk($rows, self::USER_UPDATE_BATCH_SIZE) as $chunk) {
            $usernameCases = [];
            $ipCases = [];
            $pingCases = [];
            $ids = [];
            $params = [':serverId' => $serverId, ':lastVisit' => $now];
            foreach ($chunk as $index => $row) {
                $steamUser = ':steamUser' . $index;
                $steamIp = ':steamIp' . $index;
                $steamPing = ':steamPing' . $index;
                $steamWhere = ':steamWhere' . $index;
                $username = ':username' . $index;
                $ip = ':ip' . $index;
                $ping = ':ping' . $index;
                $ids[] = $steamWhere;
                $usernameCases[] = "WHEN {$steamUser} THEN {$username}";
                $ipCases[] = "WHEN {$steamIp} THEN {$ip}";
                $pingCases[] = "WHEN {$steamPing} THEN {$ping}";
                $params[$steamUser] = $row['steam_id'];
                $params[$steamIp] = $row['steam_id'];
                $params[$steamPing] = $row['steam_id'];
                $params[$steamWhere] = $row['steam_id'];
                $params[$username] = $row['username'];
                $params[$ip] = $row['ip'];
                $params[$ping] = $row['ping'];
            }

            $sql = "UPDATE {$table} SET\n"
                . "username = CASE steam_id " . implode(' ', $usernameCases) . " ELSE username END,\n"
                . "ip = CASE steam_id " . implode(' ', $ipCases) . " ELSE ip END,\n"
                . "ping = CASE steam_id " . implode(' ', $pingCases) . " ELSE ping END,\n"
                . "server_id = :serverId, last_visit_server_at = :lastVisit\n"
                . "WHERE steam_id IN (" . implode(', ', $ids) . ")";
            $db->createCommand($sql, $params)->execute();
        }
    }
}
