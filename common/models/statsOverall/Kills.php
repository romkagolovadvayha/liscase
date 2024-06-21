<?php

namespace common\models\statsOverall;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $steam_id
 * @property string              $type
 * @property string              $dead
 * @property string              $weapon
 * @property string              $created_at
 */
class Kills extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'stats_kills';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'steam_id'   => Yii::t('common', 'Steam ID'),
            'player'     => Yii::t('common', 'Имя игрока'),
            'name'       => Yii::t('common', 'Скин'),
            'price'      => Yii::t('common', 'Цена в стиме'),
            'real_price' => Yii::t('common', 'Цена в маркете'),
            'image'      => Yii::t('common', 'Изображение'),
            'replay'     => Yii::t('common', 'Ссылка повторить покупку'),
            'created_at' => Yii::t('common', 'Тип'),
        ];
    }

    public static function getWeaponsList() {
        return [
            'default' => '/images/weapons/assultrifle.png',
            'smg.mp5' => '/images/weapons/mp5.png',
            'smg.thompson' => '/images/weapons/thompson.png',
            'rifle.ak' => '/images/weapons/assultrifle.png',
            'rifle.ak.ice' => '/images/weapons/rifle.ak.ice.128.webp',
            'rifle.ak.diver' => '/images/weapons/rifle.ak.diver.128.webp',
            'bow.hunting' => '/images/weapons/bow.png',
            'bow.compound' => '/images/weapons/compound.png',
            'crossbow' => '/images/weapons/crossbow.png',
            'rifle.semiauto' => '/images/weapons/sar.png',
            'shotgun.pump' => '/images/weapons/pumpshotgun.png',
            'rifle.bolt' => '/images/weapons/bolt.png',
            'jackhammer' => '/images/weapons/Jackhammer.png',
            'hmlmg' => '/images/weapons/hmlmg.png',
            'shotgun.waterpipe' => '/images/weapons/waterpipe.png',
            'rifle.lr300' => '/images/weapons/lr300.png',
            'pistol.revolver' => '/images/weapons/revolver.png',
            'pistol.eoka' => '/images/weapons/eoka.png',
            'pistol.prototype17' => '/images/weapons/pistol.prototype17.128.webp',
            'pistol.m92' => '/images/weapons/m92.png',
            'pistol.nailgun' => '/images/weapons/nailgun.png',
            'pistol.python' => '/images/weapons/pistol.python.128.webp',
            'shotgun.double' => '/images/weapons/double.png',
            'shotgun.spas12' => '/images/weapons/spaz12.png',
            'lmg.m249' => '/images/weapons/m249.png',
            'smg.2' => '/images/weapons/custom.png',
            'rifle.l96' => '/images/weapons/l96.png',
            'rifle.m39' => '/images/weapons/lr300.png',
            'grenade.f1' => '/images/weapons/grenade.beancan.128.webp',
            'grenade.beancan' => '/images/weapons/grenade.beancan.128.webp',
            'bone.club' => '/images/weapons/bone.club.128.webp',
            'mace' => '/images/weapons/mace.128.webp',
            'minigun' => '/images/weapons/minigun.128.webp',
            'multiplegrenadelauncher' => '/images/weapons/multiplegrenadelauncher.128.webp',
            'hammer' => '/images/weapons/hammer.128.webp',
            'rock' => '/images/weapons/rock.128.webp',
            'shotgun.m4' => '/images/weapons/shotgun.m4.128.webp',
            'spear.wooden' => '/images/weapons/spear.wooden.128.webp',
            'paddle' => '/images/weapons/paddle.128.webp',
            'hammer.salvaged' => '/images/weapons/hammer.salvaged.128.webp',
            'pickaxe' => '/images/weapons/pickaxe.128.webp',
            'hatchet' => '/images/weapons/hatchet.128.webp',
            'icepick.salvaged' => '/images/weapons/icepick.salvaged.128.webp',
            'pistol.semiauto' => '/images/weapons/p250.png',
            'salvaged.cleaver' => '/images/weapons/salvaged.cleaver.128.webp',
            'axe.salvaged' => '/images/weapons/axe.salvaged.128.webp',
            'torch' => '/images/weapons/torch.128.webp',
            'stone.pickaxe' => '/images/weapons/stone.pickaxe.128.webp',
            'knife.combat' => '/images/weapons/knife.combat.128.webp',
            'machete' => '/images/weapons/machete.128.webp',
            'salvaged.sword' => '/images/weapons/salvaged.sword.128.webp',
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

    public static function getKills($server, $player, $statsModels) {
        $server->updateDbConfig();

        /** @var Overall[] $models */
        $models = Kills::find()
                      ->cache(60*5)
                      ->andWhere(['!=', 'dead', ''])
                      ->andWhere(['OR',
                                  ['steam_id' => $player['steamid']],
                                  ['dead' => $player['steamid']]
                      ])
                      ->orderBy(['id' => SORT_DESC])
                      ->asArray()
                      ->limit(50)
                      ->all();

        $weapons = Kills::getWeaponsList();
        $scientists = Kills::getScientistsList();

        for ($i = 0; $i < count($models); $i++) {
            $model = $models[$i];
            if ($model['steam_id'] === $player['steamid']) {
                $model['name'] = $player['name'];
            }
            if ($model['dead'] === $player['steamid']) {
                $model['dead_name'] = $player['name'];
            }
            foreach ($statsModels as $statsModel) {
                if ($model['steam_id'] === $statsModel['steamid']) {
                    $model['name'] = $statsModel['name'];
                    if (!empty($model['dead_name'])) {
                        break;
                    }
                }
                if ($model['dead'] === $statsModel['steamid']) {
                    $model['dead_name'] = $statsModel['name'];
                    if (!empty($model['name'])) {
                        break;
                    }
                }
            }
            if ($model['type'] !== 'deaths' && $model['type'] !== 'suicides') {
                if (!empty($weapons[$model['weapon']])) {
                    $model['weapon_image'] = $weapons[$model['weapon']];
                } elseif (!empty($model['weapon'])) {
                    $model['weapon_image'] = $weapons['default'];
                }
            }
            if ($model['type'] === 'scientists') {
                if (!empty($scientists[$model['dead']])) {
                    $model['image'] = $scientists[$model['dead']];
                } else {
                    $model['image'] = $scientists['default'];
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
