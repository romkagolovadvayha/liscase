<?php

namespace common\models\statsOverall;

use common\components\base\ActiveRecord;
use common\models\user\Auth;
use Yii;

/**
 * @property int    $id
 * @property int    $status
 * @property string $last_visit
 * @property string $steamid
 * @property int    $connections
 * @property int    $playtime
 * @property int    $kills
 * @property int    $deaths
 * @property int    $bfired
 * @property int    $suicides
 * @property int    $wounded
 * @property int    $c4thrown
 * @property int    $satchelsthrown
 * @property int    $rocketsfired
 * @property int    $tcsdestroyed
 * @property int    $chickens
 * @property int    $boars
 * @property int    $deers
 * @property int    $horses
 * @property int    $wolves
 * @property int    $bears
 * @property int    $scientists
 * @property int    $helicopters
 * @property int    $bradleys
 * @property int    $ak47
 * @property int    $lr300
 * @property int    $m39
 * @property int    $sar
 * @property int    $m249
 * @property int    $hmlmg
 * @property int    $l96
 * @property int    $bolt
 * @property int    $mp5
 * @property int    $thompson
 * @property int    $custom
 * @property int    $pump
 * @property int    $doublebarrel
 * @property int    $spaz12
 * @property int    $m92
 * @property int    $python
 * @property int    $semipistol
 * @property int    $revolver
 * @property int    $waterpipe
 * @property int    $eoka
 * @property int    $compound
 * @property int    $crossbow
 * @property int    $bow
 * @property int    $head_hits
 * @property int    $torso_hits
 * @property int    $leftarm_hits
 * @property int    $rightarm_hits
 * @property int    $leftleg_hits
 * @property int    $rightleg_hits
 * @property int    $leftfoot_hits
 * @property int    $rightfoot_hits
 * @property int    $stones
 * @property int    $wood
 * @property int    $metal_ore
 * @property int    $metal_hq_ore
 * @property int    $sulfur_ore
 * @property int    $cloth
 * @property int    $pumpkin
 * @property int    $corn
 * @property int    $blue_berry
 * @property int    $yellow_berry
 * @property int    $red_berry
 * @property int    $white_berry
 * @property int    $potato
 * @property int    $green_berry
 * @property int    $anchovy
 * @property int    $catfish
 * @property int    $herring
 * @property int    $salmon
 * @property int    $sardine
 * @property int    $smallshark
 * @property int    $troutsmall
 * @property int    $yellowperch
 * @property int    $orangeroughy
 */
class Overall extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'stats_overall';
    }

    public static function getStats($server, $steamId = null) {
        $cacheKey = "getStats2_serverId{$server->id}_{$steamId}";
        $data = Yii::$app->cache->get($cacheKey);
        if (empty($data)) {
            $server->updateDbConfig();

            /** @var Overall[] $models */
            $models = Overall::find()
                          ->cache(60*5)
                          ->andWhere(['>=', 'playtime', 60])
                          ->asArray()
                          ->all();

            for ($i = 0; $i < count($models); $i++) {
                $item = $models[$i];
                //c4thrown + satchelsthrown * 0.2 + rocketsfired * 0.5
                $item['reider'] = round($item['c4thrown']
                                        + $item['satchelsthrown'] * 0.2
                                        + $item['rocketsfired'] * 0.5);
                //wood * 0.2 + stones * 0.3 + metal_ore * 0.5 + sulfur_ore
                $item['farmer'] = round($item['wood'] * 0.2
                                        + $item['stones'] * 0.3
                                        + $item['metal_ore'] * 0.5
                                        + $item['sulfur_ore']);
                //orangeroughy * 37 + salmon * 22 + sardine * 10 + smallshark * 45 + troutsmall * 15 + yellowperch * 25
                $item['fishing'] = round($item['anchovy'] * 10
                                         + $item['catfish'] * 32
                                         + $item['herring'] * 10
                                         + $item['orangeroughy'] * 37
                                         + $item['salmon'] * 22
                                         + $item['sardine'] * 10
                                         + $item['smallshark'] * 45
                                         + $item['troutsmall'] * 15
                                         + $item['yellowperch'] * 25);
                //chickens + boars + deers + horses + wolves + bears
                $item['hunter'] = $item['chickens']
                    + $item['boars']
                    + $item['deers']
                    + $item['horses']
                    + $item['wolves']
                    + $item['bears'];
                //cloth + pumpkin + corn + green_berry + blue_berry + yellow_berry + red_berry + white_berry + potato
                $item['fermer'] = $item['cloth']
                    + $item['pumpkin']
                    + $item['corn']
                    + $item['green_berry']
                    + $item['blue_berry']
                    + $item['yellow_berry']
                    + $item['red_berry']
                    + $item['white_berry']
                    + $item['potato'];
                $models[$i] = $item;
            }
            $data = [
                'kills' => Overall::getTopList($models, 'kills', $steamId),
                'scientists' => Overall::getTopList($models, 'scientists', $steamId),
                'playtime' => Overall::getTopList($models, 'playtime', $steamId),
                'reider' => Overall::getTopList($models, 'reider', $steamId),
                'farmer' => Overall::getTopList($models, 'farmer', $steamId),
                'fishing' => Overall::getTopList($models, 'fishing', $steamId),
                'hunter' => Overall::getTopList($models, 'hunter', $steamId),
                'fermer' => Overall::getTopList($models, 'fermer', $steamId),
                'deaths' => Overall::getTopList($models, 'deaths', $steamId),
                'models' => $models
            ];
            Yii::$app->cache->set($cacheKey, $data, 300);
        }

        if (!empty($steamId)) {
            foreach ($data['models'] as $item) {
                if (!empty($steamId) && $item['steamid'] == $steamId) {
                    $data['player'] = $item;
                    break;
                }
            }
        }

        return $data;
    }

    public static function getPlayer($server, $steamId = null) {
        $server->updateDbConfig();

        /** @var Overall[] $models */
        $models = Overall::find()
                      ->cache(60*5)
                      ->andWhere(['>=', 'playtime', 60])
                      ->asArray()
                      ->all();

        for ($i = 0; $i < count($models); $i++) {
            if (!empty($steamId) && $models[$i]['steamid'] == $steamId) {
                return $models[$i];
            }
        }

        return null;
    }

    public static function getStatsOriginal($server) {
        $server->updateDbConfig();

        /** @var Overall[] $models */
        $models = Overall::find()
                      ->cache(60*5)
                      ->andWhere(['>=', 'playtime', 60])
                      ->asArray()
                      ->all();

        return [
          'models' => $models,
        ];
    }

    /**
     * @param $models
     * @param $attrName
     * @param $steamId
     *
     * @return array
     */
    public static function getTopList($models, $attrName, $steamId = null) {
        usort($models, function ($a, $b) use ($attrName) {
            return ($b[$attrName] < $a[$attrName]) ? -1 : 1;
        });
        $position = null;
        if (!empty($steamId)) {
            $position = null;
            foreach ($models as $i => $model) {
                if ($model['steamid'] == $steamId) {
                    $position = $i + 1;
                    break;
                }
            }
        }
        $data = array_slice($models, 0, 3);

        return [
            'players' => $data,
            'attrName' => $attrName,
            'currentPosition' => $position,
        ];
    }

    /**
     * @param $steamId
     *
     * @return string
     */
    public static function getAvatar($steamId) {
        /** @var Auth $auth */
        $auth = Auth::find()
                    ->andWhere(['source_id' => $steamId])
                    ->one();

        if (!empty($auth)) {
            return $auth->user->userProfile->avatar;
        } else {
            return \common\components\oauth\Steam::getAvatar($steamId);
        }
    }

}
