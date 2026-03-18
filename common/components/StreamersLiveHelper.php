<?php

namespace common\components;

use Yii;

/**
 * Проверка статуса "в эфире" для Twitch и Kick (по возможности).
 */
class StreamersLiveHelper
{
    private const CACHE_KEY_PREFIX = 'streamers_live_';
    private const CACHE_TTL = 90; // секунд

    /**
     * Проверяет, в эфире ли Twitch-канал (user_id).
     */
    public static function isTwitchLive(string $twitchUserId): bool
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'twitch_' . $twitchUserId;
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return (bool) $cached;
        }
        $clientId = Yii::$app->settings->get('twitch_client_id');
        $clientSecret = Yii::$app->settings->get('twitch_client_secret');
        if (empty($clientId) || empty($clientSecret)) {
            return false;
        }
        try {
            $token = self::getTwitchAppToken($clientId, $clientSecret);
            if ($token === null) {
                return false;
            }
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' =>
                        "Client-ID: {$clientId}\r\n" .
                        "Authorization: Bearer {$token}\r\n",
                ],
            ]);
            $url = 'https://api.twitch.tv/helix/streams?user_id=' . rawurlencode($twitchUserId);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                Yii::$app->cache->set($cacheKey, 0, self::CACHE_TTL);
                return false;
            }
            $data = json_decode($raw, true);
            $isLive = !empty($data['data']) && is_array($data['data']);
            Yii::$app->cache->set($cacheKey, $isLive ? 1 : 0, self::CACHE_TTL);
            return $isLive;
        } catch (\Throwable $e) {
            Yii::info('StreamersLiveHelper Twitch: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Проверяет, в эфире ли Kick-канал (broadcaster_user_id).
     */
    public static function isKickLive(string $kickBroadcasterId): bool
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'kick_' . $kickBroadcasterId;
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return (bool) $cached;
        }
        try {
            $url = 'https://api.kick.com/api/v2/channels/' . rawurlencode($kickBroadcasterId);
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                Yii::$app->cache->set($cacheKey, 0, self::CACHE_TTL);
                return false;
            }
            $data = json_decode($raw, true);
            $isLive = !empty($data['livestream']['is_live']);
            Yii::$app->cache->set($cacheKey, $isLive ? 1 : 0, self::CACHE_TTL);
            return $isLive;
        } catch (\Throwable $e) {
            Yii::info('StreamersLiveHelper Kick: ' . $e->getMessage(), __METHOD__);
            Yii::$app->cache->set($cacheKey, 0, self::CACHE_TTL);
            return false;
        }
    }

    private static function getTwitchAppToken(string $clientId, string $clientSecret): ?string
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'twitch_token';
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false && is_string($cached)) {
            return $cached;
        }
        $post = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $post,
                'timeout' => 10,
            ],
        ]);
        $raw = @file_get_contents('https://id.twitch.tv/oauth2/token', false, $ctx);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        $token = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 0);
        if ($token && $expiresIn > 0) {
            Yii::$app->cache->set($cacheKey, $token, $expiresIn - 60);
        }
        return $token;
    }
}
