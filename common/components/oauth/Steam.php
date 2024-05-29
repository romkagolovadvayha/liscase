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
    protected function initUserAttributes(): array
    {
        $url = $this->getClaimedId();
        $id = preg_replace("/[^0-9]/", '', $url);
        $result = ['id' => $id];
        $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$this->key}&steamids={$id}";
        $response = Yii::$app->curl->get($apiUrl);
        $usersInfo = json_decode($response, 1)['response']['players'];
//        if (!empty($usersInfo)) {
            $result['username'] = $usersInfo[0]['personaname'];
            $result['avatar_link'] = $usersInfo[0]['avatarfull'];
//        }

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

    public static function updateUser($id) {
        $cacheKey = 'steam_updateUser_' . $id;
        if (Yii::$app->cache->get($cacheKey)) {
            return null;
        }

        $user = User::findOne($id);

        $key = Yii::$app->params['steamApiKey'];
        $apiUrl = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$key}&steamids={$user->steam_id}";
        $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
        $usersInfo = $response['response']['players'];
        $user->username = $usersInfo[0]['personaname'];
        $user->userProfile->name = $usersInfo[0]['personaname'];
        $user->userProfile->save(false);
        $user->save(false);
        Yii::$app->cache->set($cacheKey, 1, 60);
        return $user;
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultTitle()
    {
        return 'Steam';
    }
}
