<?php

namespace console\controllers;

use common\components\queue\stats\SaveStatsClansJob;
use common\models\clan\Clan;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\user\User;
use yii\base\BaseObject;
use yii\console\Controller;

class ClanController extends Controller
{

    /**
     * clan/test
     */
    public function actionTest()
    {

        /** @var Servers $server */
        $server = Servers::find()
                         ->cache(60)
                         ->andWhere(['tag' => 'max4x2'])
                         ->one();
        if (empty($server)) {
            return;
        }
        $wipeDate = $server->currentWipe();

        $params = [
            'sulfur.ore' => 10,
            'scrap' => 5,
        ];

        \Yii::$app->queueClansStats->push(new SaveStatsClansJob([
                                                                   'params' => $params,
                                                                   'wipeDate' => $wipeDate,
                                                                   'server' => $server,
                                                                   'steamId' => 76561198394504608,
                                                               ]));

    }

}