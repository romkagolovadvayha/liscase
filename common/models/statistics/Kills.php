<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\box\Drop;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $type
 * @property string $dead
 * @property string $wears
 * @property string $signs
 * @property string $weapon
 * @property string $distance
 * @property string $created_at
 * @property string $server_tag
 * @property string $wipe
 */
class Kills extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'statistics_kills';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'steam_id'   => Yii::t('common', 'Steam ID'),
            'type'   => Yii::t('common', 'Тип'),
            'dead'       => Yii::t('common', 'Противник'),
            'weapon'      => Yii::t('common', 'Оружие'),
            'distance'     => Yii::t('common', 'Дистанция'),
            'created_at' => Yii::t('common', 'Дата'),
        ];
    }

    public static function getAnimalsList() {
        return [
            'bear' => 'медведь',
            'polarbear' => 'белый медведь',
            'boar' => 'кабан',
            'chicken' => 'курица',
            'horse' => 'лошадь',
            'wolf2' => 'волк',
            'wolf' => 'волк',
            'skull.wolf' => 'волк',
            'stag' => 'олень',
            'autoturret_deployed' => 'турель',
            'zombie' => 'зомби',
            'simpleshark' => 'акула',
            'panther' => 'пантера',
            'crocodile' => 'крокодил',
            'tiger' => 'тигр',
        ];
    }

    public static function getAnimals2List() {
        return [
            'bear' => 'медведя',
            'polarbear' => 'белого медведя',
            'boar' => 'кабана',
            'chicken' => 'курицу',
            'horse' => 'лошадь',
            'wolf' => 'волка',
            'wolf2' => 'волка',
            'skull.wolf' => 'волка',
            'stag' => 'оленя',
            'zombie' => 'зомби',
            'simpleshark' => 'акулу',
            'panther' => 'пантеру',
            'crocodile' => 'крокодила',
            'tiger' => 'тигра',
        ];
    }

    public static function getScientistsList() {
        return [
            'default' => '/images/weapons/hazmatsuit_scientist.128.webp',
            //    'npc_tunneldweller' => '/assets/images/live/npc_tunneldweller.png',
            //    'npc_underwaterdweller' => '/assets/images/live/npc_underwaterdweller.png',
            'scientistnpc_heavy' => '/images/weapons/hazmatsuit_scientist_nvgm.128.webp',
        ];
    }

    /**
     * @param Servers $server
     * @param User $user
     *
     */
    public static function getKills($server, $user = null, $limit = 10) {
        $query = Kills::find()
                       ->cache(60)
                       ->andWhere(['!=', 'dead', ''])
                       ->andWhere(['server_tag' => $server->tag])
                       ->andWhere(['wipe' => $server->currentWipe()]);

        if (!empty($user)) {
            $query->andWhere(['OR',
                            ['steam_id' => $user->steam_id],
                            ['dead' => $user->steam_id]
                           ]);
        }
        $models = $query->orderBy(['created_at' => SORT_DESC])
            ->asArray()
            ->limit($limit)
            ->all();

        $weapons = [];
        foreach ($models as $model) {
            if (empty($model['weapon'])) {
                continue;
            }
            $weapons[$model['weapon']] = null;
        }
        $weapons = array_keys($weapons);
        $drops = Drop::find()
                     ->andWhere(['IN', 'eng_name', $weapons])
                     ->indexBy('eng_name')
                     ->all();

        $scientists = Kills::getScientistsList();

        for ($i = 0; $i < count($models); $i++) {
            $model = $models[$i];
            if (!empty($model['signs'])) {
                $model['signs'] = json_decode($model['signs'], 1);
            }
            $model['bot'] = false;
            if (!empty($user) && $model['steam_id'] === $user->steam_id) {
                $model['name'] = $user->username;
                $model['link'] = $user->getLink('stats');
            }
            if (!empty($user) && $model['dead'] === $user->steam_id) {
                $model['dead_name'] = $user->username;
                $model['dead_link'] = $user->getLink('stats');
            }
            if (empty($model['name']) && strlen($model['steam_id']) === 17) {
                $_user = User::findBySteamId($model['steam_id'], false, 'kills');
                $model['name'] = $_user->username;
                $model['link'] = $_user->getLink('stats');
            }
            if (empty($model['dead_name']) && strlen($model['dead']) === 17) {
                $_user = User::findBySteamId($model['dead'], false, 'kills 2');
                $model['dead_name'] = $_user->username;
                $model['dead_link'] = $_user->getLink('stats');
            }
            if ($model['type'] !== 'deaths' && $model['type'] !== 'suicides') {
                if (!empty($drops[$model['weapon']])) {
                    $model['weapon_image'] = $drops[$model['weapon']]->imageOrig->getImagePubUrl();
                    $model['weapon_name'] = $drops[$model['weapon']]->name;
                }
            }
            if ($model['type'] === 'scientists') {
                if (!empty($scientists[$model['dead']])) {
                    $model['image'] = $scientists[$model['dead']];
                    $model['bot'] = true;
                }
            }
            if ($model['type'] === 'kill') {
                if (strlen($model['steam_id']) < 10) {
                    $model['image'] = $scientists['default'];
                    $model['bot'] = true;
                }
            }
            $models[$i] = $model;
        }

        return $models;
    }

    public static function getKillsLive($server, $user = null) {
        $kills = [];
        $animals = Kills::getAnimalsList();
        $animals2 = Kills::getAnimals2List();
        $models = Kills::getKills($server, $user);
        foreach ($models as $model) {
            if (empty($model['dead_name'])) {
                $model['deadLink'] = "<span class=\"stat-block__list__name\">".Yii::t('common', 'Не известный')."</span>";
            } else {
                $model['deadLink'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['dead_link']}\">
                    {$model['dead_name']}
                </a>";
            }
            if (empty($model['name'])) {
                $model['link'] = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
            } else {
                $model['link'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['link']}\">
                    {$model['name']}
                </a>";
            }
            if (!empty($animals[$model['dead']])) {
                $model['animal'] = $animals[$model['dead']];
            }
            if (!empty($animals2[$model['dead']])) {
                $model['animal2'] = $animals2[$model['dead']];
            }
            if (empty($model['weapon_name'])) {
                $model['weapon_name'] = $model['weapon'];
            }
            $kills[] = $model;
        }
        unset($models);

        return $kills;
    }

    public static function getLive($servers, $update = false) {
        $cacheKey = 'steam_getLive_' . count($servers);
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $kills = [];
        $animals = Kills::getAnimalsList();
        $animals2 = Kills::getAnimals2List();
        foreach ($servers as $server) {
            $models = Kills::getKills($server);
            $kills[$server->id] = [];
            foreach ($models as $model) {
                if (empty($model['dead_name'])) {
                    $model['deadLink'] = "<span class=\"stat-block__list__name\">".Yii::t('common', 'Не известный')."</span>";
                } else {
                    $model['deadLink'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['dead_link']}\">
                    {$model['dead_name']}
                </a>";
                }
                if (empty($model['name'])) {
                    $model['link'] = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
                } else {
                    $model['link'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"stat-block__list__name p3 link font-medium\" href=\"{$model['link']}\">
                    {$model['name']}
                </a>";
                }
                if (!empty($animals[$model['dead']])) {
                    $model['animal'] = $animals[$model['dead']];
                }
                if (!empty($animals2[$model['dead']])) {
                    $model['animal2'] = $animals2[$model['dead']];
                }
                if (empty($model['weapon_name'])) {
                    $model['weapon_name'] = $model['weapon'];
                }
                $kills[$server->id][] = $model;
            }
            unset($models);
        }

        Yii::$app->cache->set($cacheKey, $kills, 7*24*60*60);
        return $kills;
    }
}
