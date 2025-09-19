<?php

namespace common\models\clan;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "clan".
 *
 * @property int         $id
 * @property string|null $title
 * @property string|null $description_short
 * @property string|null $description
 * @property string|null $logo_image
 * @property string|null $background_image
 * @property int|null    $user_id
 * @property int|null    $user_count
 * @property string|null $social_youtube
 * @property string|null $social_discord
 * @property string|null $social_vk
 * @property string|null $social_twitch
 * @property string|null $link_hash
 * @property string|null $created_at
 *
 * @property ClanInvite[] $clanInvites
 * @property ClanPage[] $clanPages
 * @property ClanQuestion[] $clanQuestions
 * @property ClanResource[] $clanResources
 * @property User $user
 * @property UserClan[] $userClans
 * @property UserRole[] $userRoles
 * @property ClanStats[] $clanStats
 */
class Clan extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description'], 'string'],
            [['user_id', 'user_count'], 'integer'],
            [['created_at'], 'safe'],
            [['title', 'logo_image', 'background_image', 'social_youtube', 'social_discord', 'social_vk', 'social_twitch'], 'string', 'max' => 255],
            [['description_short'], 'string', 'max' => 110],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => Yii::t('common', 'Название клана'),
            'description_short' => 'Description Short',
            'description' => 'Description',
            'logo_image' => 'Logo Image',
            'user_count' => 'Количество участников',
            'background_image' => 'Background Image',
            'user_id' => 'User ID',
            'social_youtube' => Yii::t('common', 'Ссылка на Youtube'),
            'social_discord' => Yii::t('common', 'Ссылка на Discord'),
            'social_vk' => Yii::t('common', 'Ссылка на VK'),
            'social_twitch' => Yii::t('common', 'Ссылка на Twitch'),
            'created_at' => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * Gets query for [[ClanInvites]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanInvites()
    {
        return $this->hasMany(ClanInvite::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanPages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanPages()
    {
        return $this->hasMany(ClanPage::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanStats]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanStats()
    {
        return $this->hasMany(ClanStats::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanQuestions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanQuestions()
    {
        return $this->hasMany(ClanQuestion::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanResources]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanResources()
    {
        return $this->hasMany(ClanResource::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[UserClans]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserClans()
    {
        return $this->hasMany(UserClan::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[UserRoles]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserRoles()
    {
        return $this->hasMany(UserRole::class, ['clan_id' => 'id']);
    }

    public function getLogo($cdn = true) {
        if (empty($this->logo_image)) {
            return Yii::$app->params['cdnUrl'] . '/images/design/x400.png';
        }
        if ($cdn) {
            return Yii::$app->params['cdnUrl'] . $this->logo_image;
        }
        return $this->logo_image;
    }

    public function getBackground($cdn = true) {
        if (empty($this->background_image)) {
            return Yii::$app->params['cdnUrl'] . '/images/design/x1590.png';
        }
        if ($cdn) {
            return Yii::$app->params['cdnUrl'] . $this->background_image;
        }
        return $this->background_image;
    }

    /**
     * @param Servers $server
     */
    public static function recalculate($server, $clanId) {
        /** @var ClanStats $clanStats */
        $clanStats = ClanStats::find()
                        ->andWhere(['clan_id' => $clanId])
                        ->andWhere(['server_id' => $server->id])
                        ->andWhere(['wipe' => $server->currentWipe()])
                        ->one();

        if (empty($clanStats)) {
            $clanStats = new ClanStats();
            $clanStats->clan_id = $clanId;
            $clanStats->server_id = $server->id;
            $clanStats->wipe = $server->currentWipe();
            $clanStats->created_at = date('Y-m-d H:i:s');
        }

        $clanStats->scrap = UserClanStats::getSum($server->id, $server->currentWipe(), $clanId, 'scrap');
        $clanStats->sulfur_ore = UserClanStats::getSum($server->id, $server->currentWipe(), $clanId, 'sulfur.ore');

        $clanStats->updated_at = date('Y-m-d H:i:s');
        $clanStats->save(false);
    }

    public static function calculateRating($item) {
        $result = 0;
        $result += $item['kills'] ?? 0;
        if (!empty($item['scrap']) && $item['scrap'] > 0) {
            $result += round($item['scrap'] * 0.02);
        }
        if (!empty($item['stones']) && $item['stones'] > 0) {
            $result += round($item['stones'] * 0.0002);
        }
        if (!empty($item['wood']) && $item['wood'] > 0) {
            $result += round($item['wood'] * 0.0001);
        }
        if (!empty($item['metal.ore']) && $item['metal.ore'] > 0) {
            $result += round($item['metal.ore'] * 0.0003);
        }
        if (!empty($item['sulfur.ore']) && $item['sulfur.ore'] > 0) {
            $result += round($item['sulfur.ore'] * 0.0005);
        }
        if (!empty($item['hq.metal.ore']) && $item['hq.metal.ore'] > 0) {
            $result += round($item['hq.metal.ore'] * 0.001);
        }
        if (!empty($item['nude_kills']) && $item['nude_kills'] > 0) {
            $result -= round($item['nude_kills'] * 0.5);
        }
        if (!empty($item['deaths']) && $item['deaths'] > 0) {
            $result -= round($item['deaths'] * 0.5);
        }
        if (!empty($item['c4thrown']) && $item['c4thrown'] > 0) {
            $result += round($item['c4thrown'] * 0.2);
        }
        if (!empty($item['rocket_basic']) && $item['rocket_basic'] > 0) {
            $result += round($item['rocket_basic'] * 0.1);
        }
        if (!empty($item['ammo_explosive']) && $item['ammo_explosive'] > 0) {
            $result += round($item['ammo_explosive'] * 0.008);
        }
        if (!empty($item['satchelsthrown']) && $item['satchelsthrown'] > 0) {
            $result += round($item['satchelsthrown'] * 0.08);
        }

        return $result;
    }

//    public static function getUserClansList($server, $update = false) {
//        $cacheKey = 'Clan_getUserClans_' . $server->tag;
//        if (Yii::$app->cache->get($cacheKey) && !$update) {
//            return Yii::$app->cache->get($cacheKey);
//        }
//
//        return Yii::$app->cache->get($cacheKey);
//    }

    public function getLink($key, $serverTag = null) {
        if ($key === 'profile') {
            return "/clans/{$serverTag}/{$this->link_hash}";
        }
        return null;
    }
}
