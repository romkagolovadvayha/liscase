<?php

namespace common\components\oauth;

use yii\authclient\OpenId;
use Yii;

/**
 * VKontakte allows authentication via Steam OAuth.
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
            $avatar = $usersInfo[0]['avatarfull'];
            $avatarUrl = $this->_loadImage($avatar, $id);
            $result['avatar'] = $avatarUrl;
        }

        return array_merge($result, $this->fetchAttributes());
    }

    private function _loadImage($imageUrl, $id) {
        $uploadDir = Yii::getAlias('@app/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
    }

    /**
     * {@inheritdoc}
     */
    protected function defaultTitle()
    {
        return 'Steam';
    }
}
