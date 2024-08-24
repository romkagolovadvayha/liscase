<?php

namespace common\models\statistics;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use common\models\box\Drop;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;

/**
 * @property int    $id
 * @property string $steam_id
 * @property string $type
 * @property string $dead
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
            'wolf' => 'волк',
            'stag' => 'олень',
            'autoturret_deployed' => 'турель',
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
            'stag' => 'оленя',
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
     * @param $server
     * @param User $user
     *
     */
    public static function getKills($server, $user) {
        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
        $models = Kills::find()
                       ->cache(60*5)
                       ->andWhere(['!=', 'dead', ''])
                       ->andWhere(['server_tag' => $server->tag])
                       ->andWhere(['wipe' => $wipeDate])
                       ->andWhere(['OR',
                                   ['steam_id' => $user->steam_id],
                                   ['dead' => $user->steam_id]
                                  ])
                       ->orderBy(['created_at' => SORT_DESC])
                       ->asArray()
                       ->limit(50)
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
            if ($model['steam_id'] === $user->steam_id) {
                $model['name'] = $user->username;
            }
            if ($model['dead'] === $user->steam_id) {
                $model['dead_name'] = $user->username;
            }
            if (empty($model['name']) && strlen($model['steam_id']) === 17) {
                $_user = User::findBySteamId($model['steam_id']);
                $model['name'] = $_user->username;
            }
            if (empty($model['dead_name']) && strlen($model['dead']) === 17) {
                $_user = User::findBySteamId($model['dead']);
                $model['dead_name'] = $_user->username;
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
                }
            }
            if ($model['type'] === 'kill') {
                if (strlen($model['steam_id']) < 10) {
                    $model['image'] = $scientists['default'];
                }
            }
            $models[$i] = $model;
        }

        return $models;
    }
}
