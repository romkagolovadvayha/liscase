<?php

namespace common\components\oauth;

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
        if (!empty($usersInfo)) {
            $result['username'] = $usersInfo[0]['personaname'];
            $result['avatar_link'] = $usersInfo[0]['avatarfull'];
        }

        return array_merge($result, $this->fetchAttributes());
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultTitle()
    {
        return 'Steam';
    }
}
