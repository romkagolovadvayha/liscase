<?php
namespace console\controllers;

use common\components\oauth\Steam;
use yii\console\Controller;

class RustotekaController extends Controller
{
    /**
     * rustoteka/test
     */
    public function actionTest()
    {
        echo (Steam::hasLinkProfile("76561199615706587") ? 1 : 0) . PHP_EOL;
        echo (Steam::hasLinkProfile("https://steamcommunity.com/id/eptromsa/") ? 1 : 0) . PHP_EOL;
        echo (Steam::hasLinkProfile("https://steamcommunity.com/profiles/76561199615706587") ? 1 : 0) . PHP_EOL;
        echo Steam::getSteamId("https://steamcommunity.com/profiles/76561199615706587/") . PHP_EOL;
        echo Steam::getSteamId("https://steamcommunity.com/idlk/3csdfczdxcvxvx2") . PHP_EOL;
    }
}