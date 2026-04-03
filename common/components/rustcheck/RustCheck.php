<?php

namespace common\components\rustcheck;

use Yii;

class RustCheck
{

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://rustcheatcheck.ru/panel/api';

    /**
     * SteamID64 для параметра player в API rustcheatcheck (как в плагине: UInt64 без лишних символов).
     *
     * @param mixed $steamId
     */
    public static function normalizePlayerSteamId($steamId): ?string
    {
        if ($steamId === null || $steamId === '') {
            return null;
        }
        if (is_float($steamId) || (is_string($steamId) && strpos($steamId, 'E') !== false)) {
            $steamId = sprintf('%.0f', (float) $steamId);
        }
        $s = preg_replace('/\s+/', '', trim((string) $steamId));
        if ($s === '') {
            return null;
        }
        if (preg_match('#steamcommunity\.com/profiles/(\d{8,20})#i', $s, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d{8,20}$/', $s)) {
            return $s;
        }
        if (preg_match('/(\d{15,20})/', $s, $m)) {
            return $m[1];
        }

        return $s;
    }

    /**
     * @param mixed $steamId
     *
     * @return array|null
     * @throws \Exception
     */
    public function getInfo($steamId)
    {
        $player = self::normalizePlayerSteamId($steamId);
        if ($player === null || $player === '') {
            return [];
        }

        $secretKey = Yii::$app->settings->get('banSystem_rustcheatcheck');
        if ($secretKey === null || $secretKey === '') {
            return [];
        }

        $url = $this->baseUrl
            . '?action=getInfo&key=' . rawurlencode((string) $secretKey)
            . '&player=' . rawurlencode($player);
        $response = Yii::$app->curl->get($url);
        if ($response === null || $response === '') {
            return [];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            Yii::warning('RustCheck::getInfo: invalid JSON for player ' . $player, __METHOD__);
            return [];
        }

        return $decoded;
    }

    /**
     * {@inheritdoc}
     */
    public function ban($steamId, $reason)
    {
        $player = self::normalizePlayerSteamId($steamId);
        if ($player === null || $player === '') {
            return;
        }
        $secretKey = Yii::$app->settings->get('banSystem_rustcheatcheck');
        if ($secretKey === null || $secretKey === '') {
            return;
        }
        $url = $this->baseUrl
            . '?action=addBan&key=' . rawurlencode((string) $secretKey)
            . '&player=' . rawurlencode($player)
            . '&reason=' . rawurlencode((string) $reason);
        Yii::$app->curl->get($url);
    }

    /**
     * {@inheritdoc}
     */
    public function unban($steamId)
    {
        $player = self::normalizePlayerSteamId($steamId);
        if ($player === null || $player === '') {
            return;
        }
        $secretKey = Yii::$app->settings->get('banSystem_rustcheatcheck');
        if ($secretKey === null || $secretKey === '') {
            return;
        }
        $url = $this->baseUrl
            . '?action=removeBan&key=' . rawurlencode((string) $secretKey)
            . '&player=' . rawurlencode($player);
        Yii::$app->curl->get($url);
    }

}
