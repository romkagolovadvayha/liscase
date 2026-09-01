<?php

namespace common\components\max;

use Yii;

/**
 * Настройки интеграции поддержки с MAX из site_settings/maxSupport.
 */
final class MaxSupportSettings
{
    public function isEnabled(): bool
    {
        return (string)Yii::$app->settings->get('maxSupport_enabled') === '1';
    }

    public function accessToken(): string
    {
        return trim((string)Yii::$app->settings->get('maxSupport_accessToken'));
    }

    public function chatId(): string
    {
        return trim((string)Yii::$app->settings->get('maxSupport_chatId'));
    }

    public function webhookSecret(): string
    {
        return trim((string)Yii::$app->settings->get('maxSupport_webhookSecret'));
    }

    public function defaultOperatorSteamId(): string
    {
        $steamId = trim((string)Yii::$app->settings->get('maxSupport_defaultOperatorSteamId'));

        return $steamId !== '' ? $steamId : '777';
    }

    public function operatorUserId($maxUserId): ?int
    {
        $map = self::parseOperatorMap((string)Yii::$app->settings->get('maxSupport_operatorMap'));
        $key = trim((string)$maxUserId);
        $userId = isset($map[$key]) ? (int)$map[$key] : 0;

        return $userId > 0 ? $userId : null;
    }

    /**
     * Поддерживает JSON {"MAX_ID": SITE_USER_ID} и строки MAX_ID:SITE_USER_ID.
     *
     * @return array<string, int>
     */
    public static function parseOperatorMap(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $result = [];
            foreach ($decoded as $maxUserId => $siteUserId) {
                if (is_array($siteUserId)) {
                    $maxUserId = $siteUserId['maxId'] ?? $siteUserId['max_user_id'] ?? $maxUserId;
                    $siteUserId = $siteUserId['userId'] ?? $siteUserId['user_id'] ?? null;
                }
                self::appendPair($result, $maxUserId, $siteUserId);
            }

            return $result;
        }

        $result = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^([0-9]+)\s*(?:=>|:|=)\s*([0-9]+)$/', $line, $matches)) {
                self::appendPair($result, $matches[1], $matches[2]);
            }
        }

        return $result;
    }

    public function supportWebhookUrl(): string
    {
        $baseUrl = rtrim((string)(Yii::$app->params['apiPublicUrl'] ?? ''), '/');

        return $baseUrl === '' ? '' : $baseUrl . '/v1/webhook/max/support';
    }

    private static function appendPair(array &$result, $maxUserId, $siteUserId): void
    {
        $maxUserId = trim((string)$maxUserId);
        $siteUserId = (int)$siteUserId;
        if ($maxUserId !== '' && ctype_digit($maxUserId) && $siteUserId > 0) {
            $result[$maxUserId] = $siteUserId;
        }
    }
}
