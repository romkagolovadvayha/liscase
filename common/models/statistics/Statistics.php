<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use common\models\stats\Wipe;
use common\models\user\Auth;
use Yii;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $key
 * @property int    $value
 * @property string $server_tag
 * @property string $wipe
 */
class Statistics extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'statistics';
    }

    public static function getParam($allParams, $key) {
        if (empty($allParams[$key])) {
            return 0;
        }
        if (is_object($allParams[$key])) {
            return $allParams[$key]->value;
        }
        return $allParams[$key];
    }

    public static function getStats(Servers $server, $steamId = null) {
        $cacheKey = "getStats_serverId{$server->id}_{$steamId}";
        //$data = Yii::$app->cache->get($cacheKey);
        if (empty($data)) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            /** @var Wipe[] $models */
            $statistics = Statistics::find()
                                    ->cache(180)
                                    ->andWhere(['server_tag' => $server->tag])
                                    ->andWhere(['wipe' => $wipeDate])
                                    ->asArray()
                                    ->all();

            $userList = [];
            foreach ($statistics as $item) {
                $userList[$item['steam_id']][$item['key']] = $item['value'];
            }

            $steamIds = array_keys($userList);
            $models = [];
            foreach ($steamIds as $_steamId) {
                $params = $userList[$_steamId];
                if (Statistics::getParam($params, 'playtime') <= 60) {
                    continue;
                }
                $item = [];
                $item['steam_id'] = $_steamId;
                $item['playtime'] = Statistics::getParam($params, 'playtime');
                $item['deaths'] = Statistics::getParam($params, 'deaths');
                $item['scientists'] = Statistics::getParam($params, 'scientists');
                $item['kills'] = Statistics::getParam($params, 'kills');
                //c4thrown + satchelsthrown * 0.2 + rocketsfired * 0.5
                $item['reider'] = round(Statistics::getParam($params, 'c4thrown')
                                        + Statistics::getParam($params, 'satchelsthrown') * 0.2
                                        + Statistics::getParam($params, 'rocket_basic') * 0.5
                                        + Statistics::getParam($params, 'rocket_hv') * 0.1
                                        + Statistics::getParam($params, 'rocket_fire') * 0.1
                                        + Statistics::getParam($params, 'ammo_explosive') * 0.01
                                        + Statistics::getParam($params, 'grenade.f1.deployed') * 0.05
                                        + Statistics::getParam($params, 'grenade.molotov.deployed') * 0.05
                                        + Statistics::getParam($params, 'grenade.beancan.deployed') * 0.05);
                //wood * 0.2 + stones * 0.3 + metal_ore * 0.5 + sulfur_ore
                $item['farmer'] = round(Statistics::getParam($params, 'wood') * 0.2
                                        + Statistics::getParam($params, 'stones') * 0.3
                                        + Statistics::getParam($params, 'metal_ore') * 0.5
                                        + Statistics::getParam($params, 'sulfur_ore'));
                //orangeroughy * 37 + salmon * 22 + sardine * 10 + smallshark * 45 + troutsmall * 15 + yellowperch * 25
                $item['fishing'] = round(Statistics::getParam($params, 'f_fish.anchovy') * 10
                                         + Statistics::getParam($params, 'f_fish.catfish') * 32
                                         + Statistics::getParam($params, 'f_fish.herring') * 10
                                         + Statistics::getParam($params, 'f_fish.orangeroughy') * 37
                                         + Statistics::getParam($params, 'f_fish.salmon') * 22
                                         + Statistics::getParam($params, 'f_fish.sardine') * 10
                                         + Statistics::getParam($params, 'f_fish.smallshark') * 45
                                         + Statistics::getParam($params, 'f_fish.troutsmall') * 15
                                         + Statistics::getParam($params, 'f_fish.yellowperch') * 25);
                //chickens + boars + deers + horses + wolves + bears
                $item['hunter'] = Statistics::getParam($params, 'chicken')
                    + Statistics::getParam($params, 'bear')
                    + Statistics::getParam($params, 'boar')
                    + Statistics::getParam($params, 'polarbear')
                    + Statistics::getParam($params, 'deer')
                    + Statistics::getParam($params, 'horse')
                    + Statistics::getParam($params, 'wolf');
                //cloth + pumpkin + corn + green_berry + blue_berry + yellow_berry + red_berry + white_berry + potato
                $item['fermer'] = Statistics::getParam($params, 'gathered_cloth')
                    + Statistics::getParam($params, 'gathered_pumpkin')
                    + Statistics::getParam($params, 'gathered_corn')
                    + Statistics::getParam($params, 'gathered_green.berry')
                    + Statistics::getParam($params, 'gathered_blue.berry')
                    + Statistics::getParam($params, 'gathered_yellow.berry')
                    + Statistics::getParam($params, 'gathered_red.berry')
                    + Statistics::getParam($params, 'gathered_white.berry')
                    + Statistics::getParam($params, 'gathered_black.berry')
                    + Statistics::getParam($params, 'gathered_potato');
                $models[] = $item;
            }
            $data = [
                'kills' => Statistics::getTopList($models, 'kills', $steamId),
                'scientists' => Statistics::getTopList($models, 'scientists', $steamId),
                'playtime' => Statistics::getTopList($models, 'playtime', $steamId),
                'reider' => Statistics::getTopList($models, 'reider', $steamId),
                'farmer' => Statistics::getTopList($models, 'farmer', $steamId),
                'fishing' => Statistics::getTopList($models, 'fishing', $steamId),
                'hunter' => Statistics::getTopList($models, 'hunter', $steamId),
                'fermer' => Statistics::getTopList($models, 'fermer', $steamId),
                'deaths' => Statistics::getTopList($models, 'deaths', $steamId),
                'models' => $models
            ];
            Yii::$app->cache->set($cacheKey, $data, 300);
        }

        if (!empty($steamId)) {
            foreach ($data['models'] as $item) {
                if (!empty($steamId) && $item['steam_id'] == $steamId) {
                    $data['player'] = $item;
                    break;
                }
            }
        }

        return $data;
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
                if ($model['steam_id'] == $steamId) {
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

    public static function getRaiderItem($drops, $player, $key, $score) {
        $result = [];
        $key = str_replace('.deployed', '', $key);

        if (!empty($drops[$key])) {
            $result['image'] = $drops[$key]->imageOrig->getImagePubUrl();
            $result['name'] = $drops[$key]->name;
        }
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = Statistics::getParam($player, $key);
        $result['score'] = $score;
        return $result;
    }

    public static function getFermItem($drops, $player, $key, $name) {
        $result = [];

        if (!empty($drops[$key])) {
            $result['image'] = $drops[$key]->imageOrig->getImagePubUrl();
        }
        $result['name'] = $name;
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getLevelCardItem($drops, $player, $key) {
        $result = [];

        if (!empty($drops[$key])) {
            $result['image'] = $drops[$key]->imageOrig->getImagePubUrl();
        }
        $result['name'] = Yii::t('database', $drops[$key]->name);
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getFoodItem($drops, $player, $key) {
        $result = [];

        if (!empty($drops[$key])) {
            $result['image'] = $drops[$key]->imageOrig->getImagePubUrl();
        }
        $result['name'] = Yii::t('database', $drops[$key]->name);
        $result['count'] = Statistics::getParam($player, 'mod_' . $key);
        $result['desc'] = number_format(Statistics::getParam($player, 'mod_' . $key), 0);

        return $result;
    }

    public static function getFishItem($drops, $player, $key, $name, $score) {
        $result = [];

        if (!empty($drops[$key])) {
            $result['image'] = $drops[$key]->imageOrig->getImagePubUrl();
        }
        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getFarmItem($drops, $player, $key, $name, $score) {
        $result = [];

        if (!empty($drops[$key])) {
            $result['image'] = $drops[$key]->imageOrig->getImagePubUrl();
        } else {
            print_r($key);exit;
        }
        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }
}
