<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\bans\Bans;
use common\models\invoice\Deposit;
use common\models\servers\Servers;
use Yii;
use yii\base\Component;

use common\components\bansystem\dto\RustAppPlayerResponse;

class RustApp
{

    private $_banList = [];

    public function banList() {
        $rustAppApiKey = Yii::$app->settings->get('banSystem_rustAppPrivateApiKey');
        //$servers = $this->servers();
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $serverList = [];
        foreach ($servers as $server) {
            $serverList[$server->rust_app_id] = $server->id;
        }

        self::serverBans($serverList, "Простой", 0, $rustAppApiKey);

        return $this->_banList;
    }

    private function serverBans($serverList, $projectName, $page, $rustAppApiKey) {
        $bans = $this->serverList($page, $rustAppApiKey);
        if (empty($bans)) {
            return [];
        }
        foreach ($bans as $item) {
            $banDate = null;
            $expireDate = null;
            if (!empty($item['unban_time'])) {
                $expireDate = date('Y-m-d H:i:s', $item['unban_time'] / 1000);
            }
            if (!empty($item['created_at'])) {
                $banDate = date('Y-m-d H:i:s', $item['created_at'] / 1000);
            }
            $serverId = null;
            if (!empty($item['server_ids'])) {
                $exist = false;
                foreach ($item['server_ids'] as $rustAppId) {
                    if (!empty($serverList[$rustAppId])) {
                        $exist = true;
                        $serverId = $serverList[$rustAppId];
                        break;
                    }
                }
                if (!$exist) {
                    continue;
                }
            }
            /** @var Bans $model */
            $model = Bans::find()
                         ->andWhere(['steam_id' => $item['steam_id']])
                         ->andWhere(['server_id' => $serverId])
                         ->one();

            if (!empty($model)) {
                if (empty($model->unbanned_at) && !empty($expireDate)) {
                    $model->unbanned_at = $expireDate;
                    $model->save();
                } elseif (!empty($model->unbanned_at) && empty($expireDate)) {
                    $model->unbanned_at = null;
                    $model->save();
                }
                continue;
            }

            // Validate required fields before adding to ban list
            if (empty($item['player']['steam_name']) || empty($item['steam_id'])) {
                Yii::warning('RustApp ban item missing required fields: ' . json_encode($item), 'rustapp');
                continue;
            }

            $this->_banList[] = [
                'username' => $item['player']['steam_name'] ?? 'Unknown',
                'steam_id' => $item['steam_id'] ?? null,
                'reason' => $item['reason'] ?? '',
                'ip' => $item['ban_ip'] ?? null,
                'date' => $banDate,
                'expireDate' => $expireDate,
                'server_id' => $serverId,
                'project' => $projectName,
            ];
        }
        self::serverBans($serverList, $projectName, $page + 1, $rustAppApiKey);
    }

    private function serverList($page, $rustAppApiKey) {
        try {
            $apiUrl = "https://court.rustapp.io/public/bans?sort_by=created&page=" . $page;
            $curl = clone Yii::$app->curl;
            $curl->setHeader('accept', 'application/json');
            $curl->setHeader('x-api-key', $rustAppApiKey);
            $response = json_decode($curl->get($apiUrl), true);
            
            if (isset($response['results']) && is_array($response['results'])) {
                return $response['results'];
            }
            
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage($e->getFile() . ":" . $e->getLine() . PHP_EOL . $e->getMessage());
        }
        return [];
    }

    /**
     * Получить информацию об игроке и его тиме из RustApp
     *
     * @param string $steamId
     *
     * @return RustAppPlayerResponse
     */
    public function player(string $steamId): RustAppPlayerResponse
    {
        $steamId = trim($steamId);
        if ($steamId === '') {
            return new RustAppPlayerResponse();
        }

        $rustAppApiKey = Yii::$app->settings->get('banSystem_rustAppPrivateApiKey');
        if (empty($rustAppApiKey)) {
            Yii::warning('RustApp player request skipped: empty API key', __METHOD__);
            return new RustAppPlayerResponse();
        }

        try {
            $apiUrl = "https://court.rustapp.io/public/player?steam_id=" . urlencode($steamId);

            $curl = clone Yii::$app->curl;
            $curl->setHeader('accept', 'application/json');
            $curl->setHeader('x-api-key', $rustAppApiKey);

            $response = $curl->get($apiUrl);
            if ($response === false) {
                Yii::warning('RustApp player request failed: empty response', __METHOD__);
                return new RustAppPlayerResponse();
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                Yii::warning('RustApp player invalid JSON: ' . $response, __METHOD__);
                return new RustAppPlayerResponse();
            }

            return RustAppPlayerResponse::fromArray($decoded);
        } catch (\Throwable $throwable) {
            Yii::error(
                'RustApp player request error: ' . $throwable->getMessage(),
                __METHOD__
            );
            return new RustAppPlayerResponse();
        }
    }

    /**
     * Создать бан через RustApp API
     *
     * @param string $steamId
     * @param string $reason
     * @param array  $options Доп. параметры: ban_ip, ban_ip_active, server_ids, proofs, expired_at, destroy_buildings
     *
     * @return array
     */
    public function createBan(string $steamId, string $reason, array $options = []): array
    {
        $steamId = trim($steamId);
        $reason = trim($reason);

        if ($steamId === '' || $reason === '') {
            return [
                'success' => false,
                'error' => 'invalid_params',
                'message' => 'steam_id and reason are required',
            ];
        }

        $rustAppApiKey = Yii::$app->settings->get('banSystem_rustAppPrivateApiKey');
        if (empty($rustAppApiKey)) {
            Yii::warning('RustApp createBan skipped: empty API key', __METHOD__);
            return [
                'success' => false,
                'error' => 'empty_api_key',
                'message' => 'RustApp API key is not configured',
            ];
        }

        $payload = [
            'bans' => [
                [
                    'steam_id' => $steamId,
                    'reason' => $reason,
                    'ban_ip' => "5.103.150.66",
                    'ban_ip_active' => false,
                    'server_ids' => [],
                    "proofs" => [],
                    "expired_at" => 0,
                    "destroy_buildings" => false,
                    'comment' => 'Автобан античита',
                ],
            ],
        ];

        try {
            $apiUrl = 'https://court.rustapp.io/public/bans';
            $curl = clone Yii::$app->curl;
            $curl->setHeader('accept', 'accept: */*');
            $curl->setHeader('x-api-key', $rustAppApiKey);
            $curl->setHeader('Content-Type', 'application/json');
            Yii::$app->telegramChats->sendMessage(json_encode($payload, JSON_UNESCAPED_UNICODE));
            $response = $curl->post($apiUrl, json_encode($payload, JSON_UNESCAPED_UNICODE));
            if ($response === false) {
                Yii::warning('RustApp createBan failed: empty response', __METHOD__);
                return [
                    'success' => false,
                    'error' => 'empty_response',
                    'message' => 'RustApp returned empty response',
                ];
            }
            Yii::$app->telegramChats->sendMessage($response);
            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                Yii::warning('RustApp createBan invalid JSON: ' . $response, __METHOD__);
                return [
                    'success' => false,
                    'error' => 'invalid_json',
                    'message' => 'RustApp returned invalid JSON',
                    'raw' => $response,
                ];
            }

            return [
                'success' => true,
                'data' => $decoded,
            ];
        } catch (\Throwable $throwable) {
            Yii::error('RustApp createBan error: ' . $throwable->getMessage(), __METHOD__);
            return [
                'success' => false,
                'error' => 'request_failed',
                'message' => $throwable->getMessage(),
            ];
        }
    }

    private function servers($rustAppApiKey) {
        try {
            $apiUrl = "https://court.rustapp.io/public/servers";
            $curl = clone Yii::$app->curl;
            $curl->setHeader('accept', 'application/json');
            $curl->setHeader('x-api-key', $rustAppApiKey);
            $response = json_decode($curl->get($apiUrl), 1);
            $result = [];
            foreach ($response as $item) {
                $result[$item['id']] = $item;
            }
            return $result;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage($e->getFile() . ":" . $e->getLine() . PHP_EOL . $e->getMessage());
        }
        return [];
    }

}