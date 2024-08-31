<?php
namespace console\controllers;

use common\models\servers\Servers;
use console\daemons\Battle;
use Ratchet\App;
use yii\console\Controller;

class DiscordController extends Controller
{

    /**
     * discord/update-online
     */
    public function actionUpdateOnline() {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere('discord_token IS NOT NULL')
                          ->all();

        foreach ($servers as $server) {
            $online = $server->players + $server->joined;
            $maxOnline = $server->max;
            $status = "Текущий онлайн: ${online}/${maxOnline}";
            exec("node " . __DIR__ . "/../../node/discord/src/send.js \"{$status}\" \"$server->discord_token\"");
        }
    }
}