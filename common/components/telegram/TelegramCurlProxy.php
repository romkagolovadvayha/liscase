<?php

namespace common\components\telegram;

use Yii;

/**
 * Прокси для cURL к api.telegram.org: proxy_ip / proxy_username / proxy_password из настроек
 * (как в {@see \common\components\VideoMetadataFetcher}).
 */
final class TelegramCurlProxy
{
    /**
     * @param resource $ch cURL handle
     */
    public static function applyFromSettings($ch): void
    {
        if (!Yii::$app->has('settings')) {
            return;
        }
        $proxyIp = Yii::$app->settings->get('proxy_ip');
        if (empty($proxyIp)) {
            return;
        }
        curl_setopt($ch, CURLOPT_PROXY, $proxyIp);
        $proxyUser = Yii::$app->settings->get('proxy_username');
        $proxyPass = Yii::$app->settings->get('proxy_password');
        if ($proxyUser !== null && $proxyUser !== '' && $proxyPass !== null) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUser . ':' . $proxyPass);
        }
    }
}
