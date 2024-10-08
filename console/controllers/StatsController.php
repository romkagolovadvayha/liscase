<?php

namespace console\controllers;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\statistics\Kills;
use common\models\statistics\Teams;
use yii\base\BaseObject;
use yii\console\Controller;
use Yii;

class StatsController extends Controller
{

    /**
     * stats/calculate
     * @throws \Exception
     */
    public function actionCalculate() {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                         ->all();

        foreach ($servers as $server) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            $statisticsKills = Statistics::find()
                                       ->andWhere(['server_tag' => $server->tag])
                                       ->andWhere(['wipe' => $wipeDate])
                                       ->andWhere(['key' => 'kills'])
                                       ->indexBy('steam_id')
                                       ->all();
            $statisticsDeaths = Statistics::find()
                                         ->andWhere(['server_tag' => $server->tag])
                                         ->andWhere(['wipe' => $wipeDate])
                                         ->andWhere(['key' => 'deaths'])
                                         ->indexBy('steam_id')
                                         ->all();

            $killsData = Kills::find()
                          ->select([
                                      'count' => 'COUNT(*)',
                                      'steam_id' => 'steam_id'
                                   ])
                          ->andWhere(['type' => 'kill'])
                          ->andWhere(['server_tag' => $server->tag])
                          ->andWhere(['wipe' => $wipeDate])
                          ->asArray()
                          ->groupBy(['steam_id'])
                          ->indexBy('steam_id')
                          ->all();

            $deadData = Kills::find()
                          ->select([
                                      'count' => 'COUNT(*)',
                                      'dead' => 'dead'
                                   ])
                          ->andWhere(['type' => 'kill'])
                          ->andWhere(['server_tag' => $server->tag])
                          ->andWhere(['wipe' => $wipeDate])
                          ->asArray()
                          ->groupBy(['dead'])
                          ->indexBy('dead')
                          ->all();

            foreach ($killsData as $steamId => $item) {
                if (!empty($statisticsKills[$steamId])) {
                    $statisticsKills[$steamId]->value = $item['count'];
                    $statisticsKills[$steamId]->save();
                }
            }
            foreach ($deadData as $steamId => $item) {
                if (!empty($statisticsDeaths[$steamId])) {
                    $statisticsDeaths[$steamId]->value = $item['count'];
                    $statisticsDeaths[$steamId]->save();
                }
            }
        }
    }

}
