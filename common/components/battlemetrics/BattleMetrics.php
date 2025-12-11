<?php

namespace common\components\battlemetrics;

use Yii;
use yii\base\Component;

class BattleMetrics extends Component
{
    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://api.battlemetrics.com';

    /**
     * Получение информации об игроке по SteamID
     * @param string $steamId SteamID игрока (17 цифр)
     * @return array|null
     */
    public function getPlayerInfo($steamId)
    {
        try {
            $apiKey = Yii::$app->settings->get('battlemetrics_api_key');
            if (empty($apiKey)) {
                Yii::warning("BattleMetrics: API key not configured", __METHOD__);
                return null;
            }

            // Ищем игрока по SteamID
            $url = $this->baseUrl . "/players?filter[search]={$steamId}&filter[game]=rust";
            $response = $this->sendRequest($url, $apiKey);
            
            if (empty($response) || empty($response['data']) || count($response['data']) === 0) {
                return null;
            }

            // Берем первого найденного игрока
            $playerId = $response['data'][0]['id'] ?? null;
            if (empty($playerId)) {
                return null;
            }

            // Получаем полную информацию об игроке
            $playerUrl = $this->baseUrl . "/players/{$playerId}?include=identifier";
            $playerData = $this->sendRequest($playerUrl, $apiKey);
            
            if (empty($playerData) || empty($playerData['data'])) {
                return null;
            }

            // Получаем сессии игрока (последние 10) с информацией о серверах
            $sessionsUrl = $this->baseUrl . "/players/{$playerId}/sessions?page[size]=10&sort=-start&include=server";
            $sessionsData = $this->sendRequest($sessionsUrl, $apiKey);

            // Получаем баны игрока
            $bansUrl = $this->baseUrl . "/players/{$playerId}/bans?page[size]=10&sort=-timestamp&include=server";
            $bansData = $this->sendRequest($bansUrl, $apiKey);

            return [
                'player' => $playerData['data'] ?? null,
                'sessions' => $sessionsData['data'] ?? [],
                'bans' => $bansData['data'] ?? [],
                'included' => [
                    'servers' => array_merge(
                        $sessionsData['included'] ?? [],
                        $bansData['included'] ?? []
                    ),
                ],
            ];
        } catch (\Exception $e) {
            Yii::error("BattleMetrics: Error getting player info for SteamID {$steamId}: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }

    /**
     * Отправка HTTP запроса к BattleMetrics API
     * @param string $url
     * @param string $apiKey
     * @return array|null
     */
    private function sendRequest($url, $apiKey)
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($response)) {
                Yii::warning("BattleMetrics: API request failed. HTTP Code: {$httpCode}, URL: {$url}", __METHOD__);
                return null;
            }

            return json_decode($response, true);
        } catch (\Exception $e) {
            Yii::error("BattleMetrics: Request error: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}

