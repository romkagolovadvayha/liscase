<?php

namespace console\controllers;

use common\components\web\User;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Kills;
use common\models\statistics\Teams;
use common\models\user\UserTop;
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
        ini_set('memory_limit', '512M');
        /** @var Servers[] $servers */
        $servers = Servers::find()
                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                         ->orderBy(['sort' => SORT_ASC])
                         ->all();

        foreach ($servers as $server) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            $statisticsKills = Statistics::find()
                                       ->andWhere(['server_tag' => $server->tag])
                                       ->andWhere(['wipe' => $wipeDate])
                                       ->andWhere(['key' => 'kills'])
                                       ->indexBy('steam_id')
                                       ->all();
            $statisticsNudeKills = Statistics::find()
                                       ->andWhere(['server_tag' => $server->tag])
                                       ->andWhere(['wipe' => $wipeDate])
                                       ->andWhere(['key' => 'nude_kills'])
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
                          ->andWhere('signs IS NULL')
                          ->andWhere(['server_tag' => $server->tag])
                          ->andWhere(['wipe' => $wipeDate])
                          ->asArray()
                          ->groupBy(['steam_id'])
                          ->indexBy('steam_id')
                          ->all();

            $nudeKillsData = Kills::find()
                          ->select([
                                      'count' => 'COUNT(*)',
                                      'steam_id' => 'steam_id'
                                   ])
                          ->andWhere(['type' => 'kill'])
                          ->andWhere('wears IS NULL')
                          ->andWhere('signs IS NULL')
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
//                          ->andWhere(['>', 'distance', 0])
                          ->andWhere(['server_tag' => $server->tag])
                          ->andWhere(['wipe' => $wipeDate])
                          ->asArray()
                          ->groupBy(['dead'])
                          ->indexBy('dead')
                          ->all();


            foreach ($nudeKillsData as $steamId => $item) {
                if (!empty($statisticsNudeKills[$steamId])) {
                    $statisticsNudeKills[$steamId]->value = $item['count'];
                    $statisticsNudeKills[$steamId]->save();
                } else {
                    $model = new Statistics();
                    $model->key = 'nude_kills';
                    $model->value = $item['count'];
                    $model->steam_id = $steamId;
                    $model->server_tag = $server->tag;
                    $model->wipe = $wipeDate;
                    $model->save(false);
                }
            }
            foreach ($killsData as $steamId => $item) {
                if (!empty($statisticsKills[$steamId])) {
                    $statisticsKills[$steamId]->value = $item['count'];
                    $statisticsKills[$steamId]->save();
                } else {
                    $model = new Statistics();
                    $model->key = 'kills';
                    $model->value = $item['count'];
                    $model->steam_id = $steamId;
                    $model->server_tag = $server->tag;
                    $model->wipe = $wipeDate;
                    $model->save(false);
                }
                if ($item['count'] > 24) {
                    $user = \common\models\user\User::find()
                                ->andWhere(['steam_id' => $steamId])
                                ->one();
                    if (!empty($user)) {
                        $userTop = UserTop::find()
                                          ->andWhere(['user_id' => $user->id])
                                          ->andWhere(['key' => UserTop::TYPE_KILLS])
                                          ->andWhere(['server_id' => $server->id])
                                          ->andWhere(['wipe' => $wipeDate])
                                          ->one();
                        if (!empty($userTop)) {
                            $userTop->value = $item['count'];
                            $userTop->save();
                        } else {
                            $model = new UserTop();
                            $model->key = UserTop::TYPE_KILLS;
                            $model->value = $item['count'];
                            $model->user_id = $user->id;
                            $model->server_id = $server->id;
                            $model->wipe = $wipeDate;
                            $model->save(false);
                        }
                    }
                }
            }
            foreach ($deadData as $steamId => $item) {
                if (!empty($statisticsDeaths[$steamId])) {
                    $statisticsDeaths[$steamId]->value = $item['count'];
                    $statisticsDeaths[$steamId]->save();
                } else {
                    $model = new Statistics();
                    $model->key = 'deaths';
                    $model->value = $item['count'];
                    $model->steam_id = $steamId;
                    $model->server_tag = $server->tag;
                    $model->wipe = $wipeDate;
                    $model->save();
                }
            }
        }
    }

    /**
     * stats/winner
     */
    public function actionWinner() {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $users = [];
        foreach ($servers as $server) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            /** @var Statistics[] $statisticsPlaytime */
            $statisticsPlaytime = Statistics::find()
                                          ->andWhere(['server_tag' => $server->tag])
                                          ->andWhere(['wipe' => $wipeDate])
                                          ->andWhere(['key' => 'playtime'])
                                          ->andWhere(['>=', 'value', 10*60])
                                          ->indexBy('steam_id')
                                          ->all();


            foreach ($statisticsPlaytime as $item) {
                if (empty($item->user->telegram_chat_id)) {
                    continue;
                }
                if (strtotime($item->user->last_visit_server_at) < strtotime('2024-12-27 00:00:01')) {
                    continue;
                }
                $countQuery = Reports::find()
                                     ->andWhere(['recepient_steam_id' => $item->steam_id])
                                     ->andWhere(['wipe' => $wipeDate])
                                     ->andWhere(['server_tag' => $server->tag]);

                $count = $countQuery->count();
                if ($count > 10) {
                    continue;
                }

                $users[] = [
                    'username' => $item->user->username,
                    'steam_id' => $item->steam_id,
                ];
            }
        }
        shuffle($users);
        $index = 1;
        foreach ($users as $user) {
            echo "{$index}. {$user['username']} ({$user['steam_id']})" . PHP_EOL;
            $index++;
        }

    }
}
