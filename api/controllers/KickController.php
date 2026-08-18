<?php

namespace api\controllers;

use common\models\user\User;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Контроллер для обработки Kick.com OAuth 2.1 (PKCE) callback.
 * Документация: https://docs.kick.com/getting-started/generating-tokens-oauth2-flow
 */
class KickController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * Kick OAuth callback
     */
    public function actionCallback()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $error = Yii::$app->request->get('error');

        if (!empty($error)) {
            Yii::error("Kick OAuth error: {$error}", __METHOD__);
            return [
                'success' => false,
                'error' => $error,
                'message' => 'Ошибка при авторизации Kick',
            ];
        }

        $stateCacheKey = 'kick_oauth_state_' . $state;
        $stateData = Yii::$app->cache->get($stateCacheKey);

        if (empty($state) || empty($stateData) || ($stateData['state'] ?? '') !== $state) {
            Yii::error("Kick OAuth state mismatch. State: {$state}", __METHOD__);
            return [
                'success' => false,
                'error' => 'invalid_state',
                'message' => 'Ошибка безопасности при авторизации Kick',
            ];
        }

        $userId = $stateData['user_id'] ?? null;
        $codeVerifier = $stateData['code_verifier'] ?? null;
        $returnUrl = $stateData['return_url'] ?? null;
        Yii::$app->cache->delete($stateCacheKey);

        if (empty($code) || empty($codeVerifier)) {
            return [
                'success' => false,
                'error' => 'no_code',
                'message' => 'Код авторизации Kick не получен',
            ];
        }

        $clientId = Yii::$app->settings->get('kick_client_id');
        $clientSecret = Yii::$app->settings->get('kick_client_secret');
        $baseUrl = rtrim(Yii::$app->request->hostInfo, '/');
        $redirectUri = $baseUrl . '/v1/auth/kick-callback';

        if (empty($clientId) || empty($clientSecret)) {
            Yii::error("Kick OAuth not configured", __METHOD__);
            return [
                'success' => false,
                'error' => 'not_configured',
                'message' => 'Kick OAuth не настроен',
            ];
        }

        $tokenUrl = 'https://id.kick.com/oauth/token';
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            Yii::error("Kick OAuth token error: HTTP {$httpCode}, Response: {$tokenResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'token_error',
                'message' => 'Ошибка при получении токена Kick',
            ];
        }

        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            return [
                'success' => false,
                'error' => 'no_token',
                'message' => 'Токен Kick не получен',
            ];
        }

        $userUrl = 'https://api.kick.com/public/v1/users';
        $ch = curl_init($userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token'],
        ]);
        $userResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            Yii::error("Kick API user error: HTTP {$httpCode}, Response: {$userResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'user_error',
                'message' => 'Ошибка при получении данных пользователя Kick',
            ];
        }

        $data = json_decode($userResponse, true);
        if (!is_array($data)) {
            Yii::error("Kick API user invalid JSON: {$userResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'user_error',
                'message' => 'Ошибка при разборе ответа Kick',
            ];
        }
        // Kick может вернуть: { "data": { "id", "channel": { "slug" } } }, { "data": [ {...} ] }, или slug в корне/в channel
        $kickUser = $data['data'] ?? $data;
        if (isset($kickUser[0]) && is_array($kickUser[0])) {
            $kickUser = $kickUser[0];
        }
        $kickId = isset($kickUser['id']) ? (string)$kickUser['id'] : (isset($kickUser['user_id']) ? (string)$kickUser['user_id'] : null);
        if (empty($kickId) && isset($data['id'])) {
            $kickId = (string)$data['id'];
        }
        $channel = $kickUser['channel'] ?? $data['channel'] ?? null;
        // В ответе /users slug приходит в поле name: {"data":[{"user_id":...,"name":"romkadvayha",...}]}
        $kickSlug = $kickUser['slug'] ?? $kickUser['name'] ?? $kickUser['username'] ?? $kickUser['login'] ?? $kickUser['channel_slug'] ?? $kickUser['user_name']
            ?? (is_array($channel) ? ($channel['slug'] ?? $channel['username'] ?? $channel['user_name'] ?? null) : null)
            ?? $data['slug'] ?? $data['name'] ?? $data['username'] ?? $data['channel_slug'] ?? null;
        // Если slug не нашли в ответе /users — запрашиваем канал по id (slug нужен для ссылки kick.com/romkadvayha)
        if (empty($kickSlug) && !empty($kickId)) {
            $channelResponse = $this->fetchKickChannelByUserId($kickId, $tokenData['access_token']);
            if ($channelResponse !== null) {
                $kickSlug = $channelResponse;
            }
        }
        $kickLink = !empty($kickSlug) ? 'https://kick.com/' . $kickSlug : 'https://kick.com/channel/' . $kickId;
        if (empty($kickId)) {
            Yii::error("Kick API user response missing id. Response: {$userResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'no_user_id',
                'message' => 'ID пользователя Kick не получен',
            ];
        }

        if (!empty($userId)) {
            $user = User::findOne($userId);
            if ($user) {
                $user->kick_id = $kickId;
                if ($user->save(false)) {
                    if (!empty($user->userProfile)) {
                        if ($user->userProfile->hasAttribute('kick_link')) {
                            $user->userProfile->kick_link = $kickLink;
                        }
                        $user->userProfile->save(false);
                    }
                    Yii::$app->response->format = Response::FORMAT_RAW;
                    try {
                        $redirectTo = (!empty($returnUrl) && \api\components\LinkReturnUrlHelper::isValidReturnUrl($returnUrl))
                            ? $returnUrl
                            : \api\components\LinkReturnUrlHelper::getDefaultProfileUrl();
                    } catch (\Throwable $e) {
                        Yii::error('Kick callback redirect URL error: ' . $e->getMessage(), __METHOD__);
                        $redirectTo = \api\components\LinkReturnUrlHelper::getDefaultProfileUrl();
                    }
                    return $this->redirect($redirectTo);
                }
            }
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        try {
            $redirectTo = (!empty($returnUrl) && \api\components\LinkReturnUrlHelper::isValidReturnUrl($returnUrl))
                ? $returnUrl
                : \api\components\LinkReturnUrlHelper::getDefaultProfileUrl();
        } catch (\Throwable $e) {
            Yii::error('Kick callback redirect URL error: ' . $e->getMessage(), __METHOD__);
            $redirectTo = \api\components\LinkReturnUrlHelper::getDefaultProfileUrl();
        }
        return $this->redirect($redirectTo);
    }

    /**
     * Запрос канала по broadcaster_user_id для получения slug (ссылка kick.com/{slug}).
     * API: GET /public/v1/channels?broadcaster_user_id=...
     * @param string $broadcasterUserId id канала/пользователя Kick (broadcaster_user_id)
     * @param string $accessToken Bearer-токен (для публичного запроса может не требоваться)
     * @return string|null slug или null
     */
    private function fetchKickChannelByUserId(string $broadcasterUserId, string $accessToken): ?string
    {
        $url = 'https://api.kick.com/public/v1/channels?broadcaster_user_id=' . urlencode($broadcasterUserId);
        foreach ([[ 'Authorization: Bearer ' . $accessToken ], []] as $headers) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode === 200 && !empty($body)) {
                break;
            }
        }
        if (empty($body) || $httpCode !== 200) {
            Yii::info("Kick channels by broadcaster_user_id: HTTP {$httpCode}, url={$url}", __METHOD__);
            return null;
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return null;
        }
        $data = $json['data'] ?? $json;
        $channel = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
        $slug = $channel['slug'] ?? $channel['username'] ?? $channel['user_name'] ?? null;
        if (!empty($slug) && is_string($slug)) {
            return $slug;
        }
        return null;
    }
}
