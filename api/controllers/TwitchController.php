<?php

namespace api\controllers;

use common\models\user\User;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Контроллер для обработки Twitch OAuth callback
 */
class TwitchController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * Twitch OAuth callback
     */
    public function actionCallback()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $error = Yii::$app->request->get('error');

        if (!empty($error)) {
            Yii::error("Twitch OAuth error: {$error}", __METHOD__);
            return [
                'success' => false,
                'error' => $error,
                'message' => 'Ошибка при авторизации Twitch',
            ];
        }

        $stateCacheKey = 'twitch_oauth_state_' . $state;
        $stateData = Yii::$app->cache->get($stateCacheKey);

        if (empty($state) || empty($stateData) || ($stateData['state'] ?? '') !== $state) {
            Yii::error("Twitch OAuth state mismatch. State: {$state}", __METHOD__);
            return [
                'success' => false,
                'error' => 'invalid_state',
                'message' => 'Ошибка безопасности при авторизации Twitch',
            ];
        }

        $userId = $stateData['user_id'] ?? null;
        $returnUrl = $stateData['return_url'] ?? null;
        Yii::$app->cache->delete($stateCacheKey);

        if (empty($code)) {
            return [
                'success' => false,
                'error' => 'no_code',
                'message' => 'Код авторизации Twitch не получен',
            ];
        }

        $clientId = Yii::$app->settings->get('twitch_client_id');
        $clientSecret = Yii::$app->settings->get('twitch_client_secret');
        $baseUrl = rtrim(Yii::$app->request->hostInfo, '/');
        $redirectUri = $baseUrl . '/v1/auth/twitch-callback';

        if (empty($clientId) || empty($clientSecret)) {
            Yii::error("Twitch OAuth not configured", __METHOD__);
            return [
                'success' => false,
                'error' => 'not_configured',
                'message' => 'Twitch OAuth не настроен',
            ];
        }

        $tokenUrl = 'https://id.twitch.tv/oauth2/token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            Yii::error("Twitch OAuth token error: HTTP {$httpCode}, Response: {$tokenResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'token_error',
                'message' => 'Ошибка при получении токена Twitch',
            ];
        }

        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            return [
                'success' => false,
                'error' => 'no_token',
                'message' => 'Токен Twitch не получен',
            ];
        }

        $userUrl = 'https://api.twitch.tv/helix/users';
        $ch = curl_init($userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token'],
            'Client-Id: ' . $clientId,
        ]);
        $userResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            Yii::error("Twitch API user error: HTTP {$httpCode}, Response: {$userResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'user_error',
                'message' => 'Ошибка при получении данных пользователя Twitch',
            ];
        }

        $data = json_decode($userResponse, true);
        $twitchUser = $data['data'][0] ?? null;
        $twitchId = $twitchUser['id'] ?? null;
        $twitchLogin = $twitchUser['login'] ?? null;
        if (empty($twitchId)) {
            return [
                'success' => false,
                'error' => 'no_user_id',
                'message' => 'ID пользователя Twitch не получен',
            ];
        }

        if (!empty($userId)) {
            $user = User::findOne($userId);
            if ($user) {
                $user->twitch_id = (string)$twitchId;
                    if ($user->save(false)) {
                    if (!empty($user->userProfile) && !empty($twitchLogin)) {
                        $user->userProfile->twitch_link = 'https://www.twitch.tv/' . $twitchLogin;
                        $user->userProfile->save(false);
                    }
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
