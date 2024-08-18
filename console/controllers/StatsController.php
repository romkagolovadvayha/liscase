<?php

namespace console\controllers;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Info;
use common\models\stats\Kills;
use common\models\stats\Teams;
use common\models\stats\Wipe;
use common\models\user\User;
use romkagolovadva\SourceQuery\SourceQuery;
use yii\base\BaseObject;
use yii\console\Controller;
use Yii;

class StatsController extends Controller
{
    /**
     * Обновить время на сервере
     * stats/update-playtime
     *
     * @throws \Exception
     */
    public function actionUpdatePlaytime($serverTag = 'max3')
    {
        /** @var Servers $server */
        $server = Servers::find()
                         ->andWhere(['tag' => $serverTag])
                         ->one();
        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
        $statistics = Statistics::find()
                                ->andWhere(['server_tag' => $serverTag])
                                ->andWhere(['wipe' => $wipeDate])
                                ->andWhere(['key' => 'playtime'])
                                ->andWhere(['>', 'value', 0])
                                ->all();

        /** @var Statistics $item */
        foreach ($statistics as $item) {
            $item->value = round(($item->value / 7) * 3);
            $item->save();
        }

    }

    /**
     * Информация о сервере
     * stats/get-old-stats
     *
     * @throws \Exception
     */
    public function actionGetOldStats($serverTag = 'nolimit')
    {
        /** @var Servers $server */
        $server = Servers::find()
                          ->andWhere('db_host IS NOT NULL')
                          ->andWhere(['IN', 'tag', [$serverTag]])
                          ->one();
        Yii::$app->db_server->username = $server->db_user;
        Yii::$app->db_server->password = $server->db_password;
        Yii::$app->db_server->dsn = "mysql:host={$server->db_host};dbname={$server->db_name}";
        Yii::$app->db_server->pdo = null;

        $stats = Wipe::getStats($server);

        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
        foreach ($stats['models'] as $stat) {
            $steamId = $stat['steamid'];
            $statistics = Statistics::find()
                                    ->andWhere(['steam_id' => $steamId])
                                    ->andWhere(['server_tag' => $server->tag])
                                    ->andWhere(['wipe' => $wipeDate])
                                    ->indexBy('key')
                                    ->all();

            $this->updateParam('playtime', $stat['playtime'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('kills', $stat['kills'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('deaths', $stat['deaths'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('bfired', $stat['bfired'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('suicides', $stat['suicides'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('wounded', $stat['wounded'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('c4thrown', $stat['c4thrown'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('satchelsthrown', $stat['satchelsthrown'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rocket_basic', $stat['rocketsfired'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('tcsdestroyed', $stat['tcsdestroyed'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('boar', $stat['boars'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('horse', $stat['horses'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('wolf', $stat['wolves'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('bear', $stat['bears'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('scientists', $stat['scientists'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('helicopters', $stat['helicopters'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('bradleys', $stat['bradleys'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rifle.ak', $stat['ak47'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rifle.lr300', $stat['lr300'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rifle.m39', $stat['m39'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('lmg.m249', $stat['m249'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hmlmg', $stat['hmlmg'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rifle.l96', $stat['l96'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rifle.bolt', $stat['bolt'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('smg.mp5', $stat['mp5'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('smg.thompson', $stat['thompson'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('shotgun.double', $stat['doublebarrel'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('pistol.m92', $stat['m92'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('pistol.python', $stat['python'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('pistol.semiauto', $stat['semipistol'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('shotgun.waterpipe', $stat['waterpipe'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('pistol.revolver', $stat['revolver'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('pistol.eoka', $stat['eoka'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('bow.compound', $stat['compound'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('crossbow', $stat['crossbow'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('legacy bow', $stat['bow'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_head', $stat['head_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_chest', $stat['torso_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_lefthand', $stat['leftarm_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_righthand', $stat['rightarm_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_leftleg', $stat['leftleg_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_rightleg', $stat['rightleg_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_leftfoot', $stat['leftfoot_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hits_rightfoot', $stat['rightfoot_hits'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('stones', $stat['stones'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('wood', $stat['wood'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('metal.ore', $stat['metal_ore'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('hq.metal.ore', $stat['metal_hq_ore'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('sulfur.ore', $stat['sulfur_ore'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_cloth', $stat['cloth'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_pumpkin', $stat['pumpkin'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_corn', $stat['corn'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_blue.berry', $stat['blue_berry'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_yellow.berry', $stat['yellow_berry'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_red.berry', $stat['red_berry'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_white.berry', $stat['white_berry'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_green.berry', $stat['green_berry'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('gathered_potato', $stat['potato'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.anchovy', $stat['anchovy'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.catfish', $stat['catfish'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.herring', $stat['herring'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.salmon', $stat['salmon'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.sardine', $stat['sardine'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.smallshark', $stat['smallshark'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.troutsmall', $stat['troutsmall'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.yellowperch', $stat['yellowperch'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('f_fish.orangeroughy', $stat['orangeroughy'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('crate_open', $stat['crate_open'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('barrel', $stat['barrel'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rocket_fire', $stat['rocket_fire'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('rocket_hv', $stat['rocket_hv'], $wipeDate, $server->tag, $steamId, $statistics);
            $this->updateParam('ammo_explosive', $stat['ammo_explosive'], $wipeDate, $server->tag, $steamId, $statistics);

        }

        $models = Kills::find()
                       ->orderBy(['id' => SORT_DESC])
                       ->asArray()
                       ->all();
        foreach ($models as $item) {
            $model = new \common\models\statistics\Kills();
            $model->steam_id = $item['steam_id'];
            $model->type = $item['type'];
            $model->dead = $item['dead'];
            $model->created_at = $item['created_at'];
            $model->weapon = $item['weapon'];
            $model->distance = 0;
            $model->server_tag = $server->tag;
            $model->wipe = $wipeDate;
            $model->save();
        }

        $models = Teams::find()
                       ->orderBy(['id' => SORT_DESC])
                       ->asArray()
                       ->all();
        foreach ($models as $item) {
            $model = new \common\models\statistics\Teams();
            $model->team_author = $item['team_author'];
            $model->steam_id = $item['steam_id'];
            $model->type = $item['type'];
            $model->created_at = $item['created_at'];
            $model->server_tag = $server->tag;
            $model->wipe = $wipeDate;
            $model->save();
        }
    }

    private function updateParam($key, $value, $wipeDate, $serverTag, $steamId, $statistics) {
        if ($value <= 0) {
            return;
        }
        if (!empty($statistics[$key])) {
            $statistics[$key]->value += $value;
            $statistics[$key]->save();
        } else {
            $model = new Statistics();
            $model->steam_id = $steamId;
            $model->server_tag = $serverTag;
            $model->key = $key;
            $model->value = $value;
            $model->wipe = $wipeDate;
            $model->save();
        }
    }
}
