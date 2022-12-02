<?php

namespace common\components\web;

use Yii;
use yii\web\Cookie as YiiCookie;

class Cookie
{
    /**
     * @param string $name
     * @param mixed  $value
     * @param bool   $mainDomain
     * @param mixed  $expireCountMinutes
     */
    public static function add($name, $value, $mainDomain = false, $expireCountMinutes = null)
    {
        $attributes = [
            'name'  => $name,
            'value' => $value,
        ];

        if ($mainDomain) {
            $attributes['domain'] = Yii::$app->params['cookieDomain'];
        }

        if (!empty($expireCountMinutes)) {
            $expireTime = time() + $expireCountMinutes * 60;

            $attributes['expire'] = $expireTime;
        }

        Yii::$app->response->cookies->add(new YiiCookie($attributes));
    }

    /**
     * @param string $cookieName
     *
     * @return mixed|null
     */
    public static function getValue($cookieName)
    {
        return Yii::$app->request->cookies->getValue($cookieName);
    }

    /**
     * @param string $cookieName
     */
    public static function remove($cookieName)
    {
        $cookie = Yii::$app->request->cookies->get($cookieName);
        if (empty($cookie)) {
            return;
        }

        Yii::$app->response->cookies->remove($cookie);

        if (in_array($cookieName, ['fromSwitcherUserId', 'refCode', 'promocode'])) {
            setcookie($cookieName, null, -1, '/', Yii::$app->params['cookieDomain']);
        }
    }
}