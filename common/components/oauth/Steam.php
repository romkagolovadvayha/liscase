<?php

namespace common\components\oauth;

use common\models\user\User;
use yii\authclient\OpenId;
use Yii;

/**
 * Steam allows authentication via Steam OAuth.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         'class' => 'yii\authclient\Collection',
 *         'clients' => [
 *             'steam' => [
 *                 'class' => 'common\components\oauth\Steam',
 *                 'key' => '',
 *             ],
 *         ],
 *     ]
 *     // ...
 * ]
 *
 * @author Roman Mescheryakov <rom_ik@bk.ru>
 * @since 1.0
 */
class Steam extends OpenId
{

    public $key;

    /**
     * {@inheritdoc}
     */
    public $authUrl = 'https://steamcommunity.com/openid';


    /**
     * {@inheritdoc}
     */
    protected function defaultName()
    {
        return 'steam';
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultRequestOptions()
    {
        return [
            'userAgent' => Yii::$app->name . ' OpenID Client',
            'timeout' => 30,
            'followLocation' => true,
            'Origin' => 'https://steamcommunity.com/',
            'Referer' => 'https://steamcommunity.com/',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function initUserAttributes(): array
    {
        $url = $this->getClaimedId();
        $id = preg_replace("/[^0-9]/", '', $url);
        $result = ['id' => $id];
        $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$this->key}&steamids={$id}";
        $response = (clone Yii::$app->curl)
            ->setOption(CURLOPT_PROXY, Yii::$app->settings->get('proxy_ip')) // Установка прокси
            ->setOption(CURLOPT_PROXYUSERPWD, Yii::$app->settings->get('proxy_username') . ':' . Yii::$app->settings->get('proxy_password')) // Если требуется аутентификация
            ->get($apiUrl);
        $usersInfo = json_decode($response, 1)['response']['players'];
        $result['username'] = $usersInfo[0]['personaname'];
        $result['avatar_link'] = $usersInfo[0]['avatarfull'];

        return array_merge($result, $this->fetchAttributes());
    }

    public static function getAvatar($steamId) {
        $cacheKey = 'steam_avatar_' . $steamId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $key = Yii::$app->params['steamApiKey'];
        $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$key}&steamids={$steamId}";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        $avatar = $response['response']['players'][0]['avatarfull'];
        Yii::$app->cache->set($cacheKey, $avatar, 3600);
        return $avatar;
    }

    public static function hasLinkProfile($link) {
        return strpos($link, 'steamcommunity.com') !== false && (strpos($link,'/profiles/') !== false || strpos($link,'/id/') !== false);
    }

    public static function getSteamId($text) {
        if (strpos($text,'/profiles/') !== false) {
            return preg_replace('/[^0-9]/', "", substr($text, strpos($text,'/profiles/')));
        }
        $response = Yii::$app->curl->get($text . "?xml=1");
        if (empty($response)) {
            return null;
        }
        $xml = new \SimpleXMLElement($response);
        $json = json_encode($xml, JSON_FORCE_OBJECT);
        $array = json_decode($json,TRUE);
        if (empty($array['steamID64'])) {
            return null;
        }
        return $array['steamID64'];
    }

    public static function updateUser($id) {
        $user = User::findOne($id);

        $key = Yii::$app->params['steamApiKey'];
        $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$key}&steamids={$user->steam_id}";
        $response = "";
        try {
            $response = Yii::$app->curl->get($apiUrl);
            $data = json_decode($response, 1);
            $usersInfo = $data['response']['players'];
            $user->username = $usersInfo[0]['personaname'];
            $user->userProfile->name = $usersInfo[0]['personaname'];
            $user->userProfile->save(false);
            $user->save(false);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('updateUser: ' . $response);
        }

        return $user;
    }

    public static function getInfoUser($steamId) {
        $response = "";
        try {
            $key = Yii::$app->params['steamApiKey'];
            $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$key}&steamids={$steamId}";
            $response = (clone Yii::$app->curl)
                ->setOption(CURLOPT_PROXY, Yii::$app->settings->get('proxy_ip')) // Установка прокси
                ->setOption(CURLOPT_PROXYUSERPWD, Yii::$app->settings->get('proxy_username') . ':' . Yii::$app->settings->get('proxy_password')) // Если требуется аутентификация
                ->get($apiUrl);
            $data = json_decode($response, 1);
            $usersInfo = $data['response']['players'];
            return $usersInfo;
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage('getInfoUser: ' . $response);
        }
        return null;
    }

    public static function getGameInfo($steamId) {
        $key = Yii::$app->params['steamApiKey'];
        $apiUrl = "https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/?key={$key}&steamid={$steamId}&include_played_free_games=1";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        if (empty($response['response'])) {
            return [];
        }
        return $response['response']['games'];
    }

    public static function getBansGGRust($steamId) {
        $bans = [];
        $server = Steam::getGGRustStats('russian_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'GGRust Российский',
            ];
        }
        $server = Steam::getGGRustStats('moscow_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'GGRust Московксий',
            ];
        }
        $server = Steam::getGGRustStats('krasnodar_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'GGRust Краснодарский',
            ];
        }
        $server = Steam::getGGRustStats('piter_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'GGRust Классика X3',
            ];
        }
        $server = Steam::getGGRustStats('made_in_russia_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'GGRust Классика X2',
            ];
        }
        $server = Steam::getSlabiyStats('slabiy1_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'Слабый 1',
            ];
        }
        $server = Steam::getSlabiyStats('slabiy2_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'Слабый 2',
            ];
        }
        $server = Steam::getSlabiyStats('slabiy3_banlist');
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'Слабый 3',
            ];
        }

        return $bans;
    }
    public static function getBansRustRoom($steamId) {
        $bans = [];
        $list = Steam::getRustRoomBans();
        foreach ($list as $item) {
            if ($item['player_id'] == $steamId) {
                $exd = 'Навсегда';
                if ($item['duration'] > 0) {
                    $exd = round(($item['duration'] /60/60/24)) . " д.";
                }
                $bans[] = [
                    'reason' => $item['reason'],
                    'date' => $item['banned_at'],
                    'expireDate' => $exd,
                    'server' => 'Rust Room',
                ];
                break;
            }
        }

        return $bans;
    }
    public static function getBansRustUssr($steamId) {
        $bans = [];
        $server = Steam::getRustUssrBans();
        if (!empty($server[$steamId])) {
            $bans[] = [
                'reason' => $server[$steamId]['Reason'],
                'date' => $server[$steamId]['BanDate'],
                'expireDate' => $server[$steamId]['ExpireDate'],
                'server' => 'Rust USSR',
            ];
        }

        return $bans;
    }
    public static function getBansMagicRust($steamId) {
        $bans = [];
        $list = Steam::getMagicRustBans();
        foreach ($list as $item) {
            if ($item['steamid'] == $steamId) {
                $exd = 'Навсегда';
                $bans[] = [
                    'reason' => $item['reason'],
                    'date' => $item['time'],
                    'expireDate' => $exd,
                    'server' => 'MagicRust',
                ];
                break;
            }
        }

        return $bans;
    }
    public static function getBansRust($steamId) {
        $bans = [];
        $list = Steam::getBroRustBans();
        foreach ($list as $item) {
            $date = new \DateTime($item['BanDate']);
            $unbanned = 'Никогда';
            if (!empty($item['UnBanDate'])) {
                $date2 = new \DateTime($item['UnBanDate']);
                $unbanned = $date2->format('d.m.Y H:i:s');
            }
            if ($item['UserID'] == $steamId) {
                $exd = 'Навсегда';
                $bans[] = [
                    'reason' => $item['BanReason'],
                    'date' => $date->format('d.m.Y H:i:s'),
                    'expireDate' => $unbanned,
                    'server' => $item['Server'],
                ];
                break;
            }
        }

        return $bans;
    }

    public static function getGGRustStats($stable = 'russian_banlist') {
        $cacheKey = 'steam_getGGRustStats_' . $stable;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "https://stats.ggrust.ru/serverbanlist.php?table={$stable}";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        Yii::$app->cache->set($cacheKey, $response, 60);
        return $response;
    }

    public static function getSlabiyStats($stable = 'russian_banlist') {
        $cacheKey = 'steam_getSlabiyStats_' . $stable;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "https://rustaria.ru/slabiy/serverbanlist.php?table={$stable}";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        Yii::$app->cache->set($cacheKey, $response, 60);
        return $response;
    }

    public static function getRustRoomBans() {
        $cacheKey = 'steam_getRustRoomBans';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "https://dev.rustroom.ru/getBanList.php";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        Yii::$app->cache->set($cacheKey, $response, 60);
        return $response;
    }

    public static function getRustUssrBans() {
        $cacheKey = 'steam_getRustUssrBans';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "https://rustussr.ru/serverbanlist.php";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        Yii::$app->cache->set($cacheKey, $response, 60);
        return $response;
    }

    public static function getMagicRustBans() {
        $cacheKey = 'steam_getMagicRustBans';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "https://vk.magicrust.ru/api/getBans";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        Yii::$app->cache->set($cacheKey, $response, 60);
        return $response;
    }

    public static function getBroRustBans() {
        $cacheKey = 'steam_getBroRustBans';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $apiUrl = "https://api.brorust.com/server/ban-list?page=1&linePerPage=2000";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1)['success']['payload']['data'];
        Yii::$app->cache->set($cacheKey, $response, 60);
        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultTitle()
    {
        return 'Steam';
    }
}
