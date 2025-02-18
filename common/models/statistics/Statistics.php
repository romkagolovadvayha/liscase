<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\models\box\Drop;
use common\models\servers\Servers;
use common\models\stats\Wipe;
use common\models\user\Auth;
use common\models\user\User;
use common\models\user\UserTree;
use Yii;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $key
 * @property int    $value
 * @property string $server_tag
 * @property string $wipe
 *
 * @property User    $user
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

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['steam_id' => 'steam_id']);
    }

    public static function getParam($allParams, $key) {
        if ($key == 'kd') {
            $kills = Statistics::getParam($allParams, 'kills');
            $deaths = Statistics::getParam($allParams, 'deaths');
            if ($kills == 0 || $deaths == 0) {
                return 0;
            }
            return $kills / $deaths;
        }
        if (empty($allParams[$key])) {
            return 0;
        }
        if (is_object($allParams[$key])) {
            return $allParams[$key]->value;
        }
        if (is_array($allParams[$key])) {
            return $allParams[$key]['value'];
        }
        return $allParams[$key];
    }

    public static function getImage($images, $key) {
        if (empty($images[$key])) {
            return '/uploads/drop/870_7aca7dcc75a50be0c7bcf772460d2018.png';
        }
        return $images[$key];
    }

    public static function getName($names, $key) {
        if (empty($names[$key])) {
            return Yii::t('common', 'Без названия');
        }
        return Yii::t('database', $names[$key]);
    }

    public static function getPlayerStats(Servers $server, $steamId, $wipe) {
        $statistics = Statistics::find()
            ->cache(60)
            ->select(['value', 'key'])
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['server_tag' => $server->tag])
            ->andWhere(['wipe' => $wipe])
            ->indexBy('key')
            ->asArray()
            ->all();

        return $statistics;
    }

    public static function getStats(Servers $server, $steamId = null, $all = true, $wipeDate = null, $cache = true) {
        ini_set('memory_limit', '512M');
        $cacheKey = "getStats_data_serverId{$server->id}_" . ($all ? 1 : 0);
        $data = null;
        if ($cache) {
            $data = Yii::$app->cache->get($cacheKey);
        }
        try {
            if (empty($data)) {
                if (empty($wipeDate)) {
                    $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime(
                            $server->next_wipe
                        ))->format('Y-m-d');
                }
                /** @var Wipe[] $models */
                $statistics = Statistics::find()
                                        ->cache(3*60)
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
                    if (!$all && Statistics::getParam($params, 'playtime') <= 60) {
                        continue;
                    }
                    $item = $params;
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
                    $item['farmer'] = round(Statistics::getParam($params, 'wood') * 0.05
                                            + Statistics::getParam($params, 'stones') * 0.3
                                            + Statistics::getParam($params, 'metal.ore') * 0.5
                                            + Statistics::getParam($params, 'sulfur.ore'));
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
                        + Statistics::getParam($params, 'wolf2')
                        + Statistics::getParam($params, 'wolf');
                    //cloth + pumpkin + corn + green_berry + blue_berry + yellow_berry + red_berry + white_berry + potato
                    $item['fermer'] = Statistics::getParam($params, 'gathered_cloth') * 0.05
                        + Statistics::getParam($params, 'gathered_pumpkin') * 0.5
                        + Statistics::getParam($params, 'gathered_corn') * 0.3
                        + Statistics::getParam($params, 'gathered_green.berry') * 0.5
                        + Statistics::getParam($params, 'gathered_blue.berry') * 0.5
                        + Statistics::getParam($params, 'gathered_yellow.berry') * 0.5
                        + Statistics::getParam($params, 'gathered_red.berry') * 0.5
                        + Statistics::getParam($params, 'gathered_white.berry') * 0.5
                        + Statistics::getParam($params, 'gathered_black.berry') * 1
                        + Statistics::getParam($params, 'gathered_potato') * 0.4;
                    $models[] = $item;
                }
                $data = [
                    'kills' => Statistics::getTopList($models, 'kills'),
                    'scientists' => Statistics::getTopList($models, 'scientists'),
                    'playtime' => Statistics::getTopList($models, 'playtime'),
                    'reider' => Statistics::getTopList($models, 'reider'),
                    'farmer' => Statistics::getTopList($models, 'farmer'),
                    'fishing' => Statistics::getTopList($models, 'fishing'),
                    'hunter' => Statistics::getTopList($models, 'hunter'),
                    'fermer' => Statistics::getTopList($models, 'fermer'),
                    'deaths' => Statistics::getTopList($models, 'deaths'),
                    'models' => $models
                ];
                Yii::$app->cache->set($cacheKey, $data, 15 * 60);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("getStats {$server->tag}: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }

        if (!empty($steamId)) {
            foreach ($data['models'] as $item) {
                if (!empty($steamId) && $item['steam_id'] == $steamId) {
                    $item['user'] = \common\models\user\User::findBySteamId($item['steam_id']);
                    $data['player'] = $item;
                    break;
                }
            }
        }

        return $data;
    }

    public static function getTopWidgetItem($key, $stats, $index = 0) {
        if (empty($stats[$key])) {
            return [];
        }

        $item = $stats[$key]['players'][$index];
        $item['total_score'] = $item[$key];
        $item['user'] = User::findBySteamId($item['steam_id']);

        return $item;
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
        $data = [];
        foreach ($models as $i => $item) {
            if ($i <= 2) {
                $item['user'] = \common\models\user\User::findBySteamId($item['steam_id']);
            }
            $data[] = $item;
        }

        return [
            'players' => $data,
            'attrName' => $attrName,
        ];
    }

    public static function getRaiderItem($names, $images, $player, $key, $score) {
        $result = [];
        $key = str_replace('.deployed', '', $key);
        $result['image'] = Statistics::getImage($images, $key);
        $result['name'] = Statistics::getName($names, $key);
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = Statistics::getParam($player, $key);
        $result['score'] = $score;
        return $result;
    }

    public static function getFermItem($images, $player, $key, $name, $score) {
        $result = [];

        $result['image'] = Statistics::getImage($images, $key);
        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getLevelCardItem($images, $names, $player, $key) {
        $result = [];

        $result['name'] = Yii::t('database', Statistics::getParam($names, $key));
        $result['image'] = Statistics::getParam($images, $key);
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getFoodItem($images, $names, $player, $key) {
        $result = [];

        $result['count'] = Statistics::getParam($player, 'mod_' . $key);
        $result['image'] = Statistics::getImage($images, $key);
        $result['name'] = Statistics::getName($names, $key);
        $result['desc'] = number_format(Statistics::getParam($player, 'mod_' . $key), 0);

        return $result;
    }

    public static function getMedicalItem($images, $names, $player, $key) {
        $result = [];

        $result['count'] = Statistics::getParam($player, $key['param']);
        $result['image'] = Statistics::getImage($images, $key['key']);
        $result['name'] = Statistics::getName($names, $key['key']);
        $result['desc'] = number_format(Statistics::getParam($player, $key['param']), 0);

        return $result;
    }

    public static function getFishItem($images, $player, $key, $name, $score) {
        $result = [];

        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['image'] = Statistics::getImage($images, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function getFarmItem($images, $names, $player, $key, $name, $score) {
        $result = [];
        $result['image'] = Statistics::getImage($images, $key);
        $result['name'] = $name;
        $result['score'] = $score;
        $result['count'] = Statistics::getParam($player, $key);
        $result['desc'] = number_format(Statistics::getParam($player, $key), 0);

        return $result;
    }

    public static function projectStats($update = false) {
        $cacheKey = 'Statistics_projectStats_';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            //return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        $result['users'] = User::find()
            ->count();

        $result['online'] = Servers::find()
            ->sum('players + joined') ?? 0;

        $result['count'] = Servers::find()
            ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
            ->count();

        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->cache(60)
            ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
            ->all();
        $result['servers'] = [];
        foreach ($servers as $server) {
            if (empty($result['servers'][$server->id])) {
                $result['servers'][$server->id] = [];
            }
            $result['servers'][$server->id]['ping'] = User::find()
                                                          ->andWhere(['>=', 'last_visit_server_at', date('Y-m-d H:i:s', time() - 5 * 60)])
                                                          ->andWhere(['server_id' => $server->id])
                                                          ->andWhere(['status' => User::STATUS_ACTIVE])
                                                          ->average('ping') ?? 0;
        }

        Yii::$app->cache->set($cacheKey, $result, 7*24*60*60);
        return $result;
    }

    public static function productsImages($update = false) {
        $cacheKey = 'Statistics_productsImages_';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->andWhere(['<>', 'eng_name', ''])
            ->all();

        foreach ($drops as $item) {
            $result[$item->eng_name] = $item->image();
        }

        Yii::$app->cache->set($cacheKey, $result, 30*60);
        return $result;
    }

    public static function productsNames($update = false) {
        $cacheKey = 'Statistics_productsNames_';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = [];

        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->andWhere(['<>', 'eng_name', ''])
            ->all();

        foreach ($drops as $item) {
            $result[$item->eng_name] = $item->name;
        }

        Yii::$app->cache->set($cacheKey, $result, 30*60);
        return $result;
    }
}
