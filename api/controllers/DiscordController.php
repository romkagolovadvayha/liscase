<?php

namespace api\controllers;

use common\components\queue\process\DiscordRolesUserJob;
use common\models\user\User;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Контроллер для обработки Discord OAuth callback
 */
class DiscordController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * Discord OAuth callback
     * Обрабатывает callback от Discord и сохраняет discord_id в сессии для последующей привязки
     */
    public function actionCallback()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $error = Yii::$app->request->get('error');

        if (!empty($error)) {
            Yii::error("Discord OAuth error: {$error}", __METHOD__);
            return [
                'success' => false,
                'error' => $error,
                'message' => 'Ошибка при авторизации Discord',
            ];
        }

        // Проверяем state для защиты от CSRF (используем кеш, так как api и frontend имеют разные сессии)
        $stateCacheKey = 'discord_oauth_state_' . $state;
        $stateData = Yii::$app->cache->get($stateCacheKey);
        
        if (empty($state) || empty($stateData) || $stateData['state'] !== $state) {
            Yii::$app->telegramChats->sendMessage("Discord OAuth state mismatch. State: {$state}, Saved: " . ($stateData ? json_encode($stateData) : 'empty'));
            return [
                'success' => false,
                'error' => 'invalid_state',
                'message' => 'Ошибка безопасности при авторизации Discord',
            ];
        }
        
        $userId = $stateData['user_id'] ?? null;
        $returnUrl = $stateData['return_url'] ?? null;
        Yii::$app->cache->delete($stateCacheKey);

        if (empty($code)) {
            return [
                'success' => false,
                'error' => 'no_code',
                'message' => 'Код авторизации Discord не получен',
            ];
        }

        $clientId = Yii::$app->settings->get('discord_client_id');
        $clientSecret = Yii::$app->settings->get('discord_client_secret');
        // Должен совпадать с redirect_uri в v1/AuthController::actionDiscord() (Discord сверяет байт-в-байт)
        $baseUrl = rtrim(Yii::$app->request->hostInfo, '/');
        $redirectUri = $baseUrl . '/v1/auth/discord-callback';
        
        Yii::$app->telegramChats->sendMessage("Discord OAuth callback: code=" . (!empty($code) ? 'received' : 'empty') . ", state={$state}, userId={$userId}, redirectUri={$redirectUri}");

        if (empty($clientId) || empty($clientSecret)) {
            Yii::error("Discord OAuth not configured", __METHOD__);
            return [
                'success' => false,
                'error' => 'not_configured',
                'message' => 'Discord OAuth не настроен',
            ];
        }

        // Обмениваем код на токен
        $tokenUrl = 'https://discord.com/api/oauth2/token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::$app->telegramChats->sendMessage("Discord OAuth token error: HTTP {$httpCode}, Response: {$tokenResponse}, cURL Error: {$curlError}, redirectUri: {$redirectUri}");
            return [
                'success' => false,
                'error' => 'token_error',
                'message' => 'Ошибка при получении токена Discord',
            ];
        }

        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            return [
                'success' => false,
                'error' => 'no_token',
                'message' => 'Токен Discord не получен',
            ];
        }

        // Получаем информацию о пользователе Discord
        $userUrl = 'https://discord.com/api/v10/users/@me';
        $ch = curl_init($userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token'],
        ]);

        $userResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::error("Discord API user error: HTTP {$httpCode}, Response: {$userResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'user_error',
                'message' => 'Ошибка при получении данных пользователя Discord',
            ];
        }

        $discordUser = json_decode($userResponse, true);
        if (empty($discordUser['id'])) {
            return [
                'success' => false,
                'error' => 'no_user_id',
                'message' => 'ID пользователя Discord не получен',
            ];
        }

        // Получаем user_id из кеша (так как api и frontend имеют разные сессии)
        if (empty($userId)) {
            $cacheKey = 'discord_oauth_user_id_' . ($stateData['user_id'] ?? '');
            $userId = Yii::$app->cache->get($cacheKey);
            if ($userId) {
                Yii::$app->cache->delete($cacheKey);
            }
        }
        
        if (!empty($userId)) {
            $user = User::findOne($userId);
            if ($user) {
                $user->discord_id = $discordUser['id'];
                if ($user->save(false)) {
                    if (Yii::$app->has('queueProcess')) {
                        Yii::$app->queueProcess->push(new DiscordRolesUserJob(['userId' => $user->id]));
                    }
                    Yii::$app->session->remove('discord_oauth_user_id');
                    Yii::$app->response->format = Response::FORMAT_RAW;
                    $redirectTo = (!empty($returnUrl) && \api\components\LinkReturnUrlHelper::isValidReturnUrl($returnUrl))
                        ? $returnUrl
                        : \api\components\LinkReturnUrlHelper::getDefaultProfileUrl();
                    return $this->redirect($redirectTo);
                }
            }
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        $redirectTo = (!empty($returnUrl) && \api\components\LinkReturnUrlHelper::isValidReturnUrl($returnUrl))
            ? $returnUrl
            : \api\components\LinkReturnUrlHelper::getDefaultProfileUrl();
        return $this->redirect($redirectTo);
    }
}

