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
        curl_close($ch);

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
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::error("Kick API user error: HTTP {$httpCode}, Response: {$userResponse}", __METHOD__);
            return [
                'success' => false,
                'error' => 'user_error',
                'message' => 'Ошибка при получении данных пользователя Kick',
            ];
        }

        $data = json_decode($userResponse, true);
        $kickUser = isset($data['data']) ? $data['data'] : $data;
        $kickId = isset($kickUser['id']) ? (string)$kickUser['id'] : (isset($data['id']) ? (string)$data['id'] : null);
        $kickSlug = $kickUser['slug'] ?? $kickUser['username'] ?? $kickUser['login'] ?? $data['slug'] ?? $data['username'] ?? null;
        if (empty($kickId)) {
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
                        $user->userProfile->kick_link = !empty($kickSlug)
                            ? 'https://kick.com/' . $kickSlug
                            : 'https://kick.com/channel/' . $kickId;
                        $user->userProfile->save(false);
                    }
                    Yii::$app->response->format = Response::FORMAT_RAW;
                    $frontendUrl = Yii::$app->params['homePage'] ?? $baseUrl;
                    return $this->redirect(rtrim($frontendUrl, '/') . '/profile');
                }
            }
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        $frontendUrl = Yii::$app->params['homePage'] ?? $baseUrl;
        return $this->redirect(rtrim($frontendUrl, '/') . '/profile');
    }
}
