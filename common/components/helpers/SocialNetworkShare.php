<?php

namespace common\components\helpers;

use Yii;
use yii\helpers\ArrayHelper;

class SocialNetworkShare
{
    const VK       = 1;
    const FACEBOOK = 2;
    const TWITTER  = 3;
    const WHATSAPP = 4;
    const TELEGRAM = 5;

    /**
     * @param int    $target
     * @param string $url
     *
     * @return string
     */
    public static function getUrl($target, $url)
    {
        $url = urlencode($url);

        $urls = [
            self::VK       => 'https://vk.com/share.php?url=' . $url,
            self::FACEBOOK => 'https://facebook.com/sharer.php?src=sp&u=' . $url,
            self::TWITTER  => 'https://twitter.com/intent/tweet?url=' . $url,
            self::WHATSAPP => 'https://api.whatsapp.com/send?text=' . $url,
            self::TELEGRAM => 'https://t.me/share/url?url=' . $url,
        ];

        return ArrayHelper::getValue($urls, $target);
    }
}