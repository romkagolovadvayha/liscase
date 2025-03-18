<?php

namespace common\models\user;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "user_top".
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $key
 * @property float|null $value
 * @property int $server_id
 * @property string|null $wipe
 *
 * @property Servers $server
 * @property User $user
 */
class UserTop extends \yii\db\ActiveRecord
{
    const TYPE_REIDER = 'reider';
    const TYPE_KILLS = 'kills';
    const TYPE_SCIENTISTS = 'scientists';
    const TYPE_PLAYTIME = 'playtime';
    const TYPE_FARMER = 'farmer';
    const TYPE_FISHING = 'fishing';
    const TYPE_HUNTER = 'hunter';
    const TYPE_FERMER = 'fermer';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_top';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'server_id'], 'required'],
            [['user_id', 'server_id'], 'integer'],
            [['value'], 'number', 'min' => 0.01],
            [['key', 'wipe'], 'string', 'max' => 255],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
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
            'user_id' => 'User ID',
            'key' => 'Key',
            'value' => 'Value',
            'server_id' => 'Server ID',
            'wipe' => 'Wipe',
        ];
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
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
     * @param User $user
     * @param string $param
     * @param string $value
     * @param Servers $server
     * @param string $wipeDate
     */
    public static function updateTop($userId, $param, $value, $serverId, $wipeDate) {
        foreach (self::getRaiting() as $key => $item) {
            if (empty($item[$param])) {
                continue;
            }
            $amount = $item[$param] * $value;
            UserTop::setTop($userId, $key, $amount, $serverId, $wipeDate);
        }
    }

    /**
     * @param User $user
     * @param string $key
     * @param float $value
     * @param Servers $server
     * @param string $wipeDate
     */
    public static function setTop($userId, $key, $value, $serverId, $wipeDate) {
        /** @var UserTop $userTop */
        $userTop = UserTop::find()
            ->andWhere(['user_id' => $userId])
            ->andWhere(['key' => $key])
            ->andWhere(['server_id' => $serverId])
            ->andWhere(['wipe' => $wipeDate])
            ->one();

        if (!empty($userTop)) {
            $userTop->value += $value;
            $userTop->save();
            return;
        }

        $userTop = new UserTop();
        $userTop->user_id = $userId;
        $userTop->key = $key;
        $userTop->value = $value;
        $userTop->server_id = $serverId;
        $userTop->wipe = $wipeDate;
        $userTop->save();
    }

    public static function getRaitingLabel() {
        return [
            UserTop::TYPE_REIDER => Yii::t('common', 'Рейдер'),
            UserTop::TYPE_KILLS => Yii::t('common', 'Убийств'),
            UserTop::TYPE_SCIENTISTS => Yii::t('common', 'Мирный'),
            UserTop::TYPE_PLAYTIME => Yii::t('common', 'Онлайн'),
            UserTop::TYPE_FARMER => Yii::t('common', 'Фармер'),
            UserTop::TYPE_FISHING => Yii::t('common', 'Рыбак'),
            UserTop::TYPE_HUNTER => Yii::t('common', 'Охотник'),
            UserTop::TYPE_FERMER => Yii::t('common', 'Фермер'),
        ];
    }

    public static function getTopsLabel() {
        return [
            UserTop::TYPE_REIDER => Yii::t('common', 'Лучший рейдер'),
            UserTop::TYPE_KILLS => Yii::t('common', 'Лучший киллер'),
            UserTop::TYPE_SCIENTISTS => Yii::t('common', 'Лучший мирный'),
            UserTop::TYPE_PLAYTIME => Yii::t('common', 'Топ по онлайну'),
            UserTop::TYPE_FARMER => Yii::t('common', 'Лучший фармер'),
            UserTop::TYPE_FISHING => Yii::t('common', 'Лучший рыбак'),
            UserTop::TYPE_HUNTER => Yii::t('common', 'Лучший охотник'),
            UserTop::TYPE_FERMER => Yii::t('common', 'Лучший фермер'),
        ];
    }

    public static function getRaitingKeys() {
        $list = self::getRaiting();
        $result = [];
        foreach ($list as $item) {
            foreach ($item as $key => $_item) {
                $result[] = $key;
            }
        }
        return $result;
    }

    public static function getRaiting() {
        return [
          UserTop::TYPE_REIDER => [
            'c4thrown' => 1,
            'satchelsthrown' => 0.2,
            'rocket_basic' => 0.5,
            'rocket_hv' => 0.1,
            'rocket_fire' => 0.1,
            'ammo_explosive' => 0.01,
            'grenade.f1.deployed' => 0.05,
            'grenade.molotov.deployed' => 0.05,
            'grenade.beancan.deployed' => 0.05,
          ],
          UserTop::TYPE_KILLS => [
            'kills' => 1,
          ],
          UserTop::TYPE_SCIENTISTS => [
            'scientists' => 1,
          ],
          UserTop::TYPE_PLAYTIME => [
            'playtime' => 1,
          ],
          UserTop::TYPE_FARMER => [
            'wood' => 0.05,
            'stones' => 0.3,
            'metal.ore' => 0.5,
            'sulfur.ore' => 1,
          ],
          UserTop::TYPE_FISHING => [
            'f_fish.anchovy' => 10,
            'f_fish.catfish' => 32,
            'f_fish.herring' => 10,
            'f_fish.orangeroughy' => 37,
            'f_fish.salmon' => 22,
            'f_fish.sardine' => 10,
            'f_fish.smallshark' => 45,
            'f_fish.troutsmall' => 15,
            'f_fish.yellowperch' => 25,
          ],
          UserTop::TYPE_HUNTER => [
              'chicken' => 1,
              'bear' => 1,
              'boar' => 1,
              'polarbear' => 1,
              'stag' => 1,
              'horse' => 1,
              'wolf2' => 1,
              'wolf' => 1,
              'simpleshark' => 1,
          ],
          UserTop::TYPE_FERMER => [
              'gathered_cloth' => 0.05,
              'gathered_pumpkin' => 0.5,
              'gathered_corn' => 0.3,
              'gathered_green.berry' => 0.5,
              'gathered_blue.berry' => 0.5,
              'gathered_yellow.berry' => 0.5,
              'gathered_red.berry' => 0.5,
              'gathered_white.berry' => 0.5,
              'gathered_black.berry' => 1,
              'gathered_potato' => 0.4,
              'gathered_orchid' => 0.3,
              'gathered_rose' => 0.3,
              'gathered_sunflower' => 0.3,
              'gathered_wheat' => 0.3,
          ],
        ];
    }

    public function keyName()
    {
        return ArrayHelper::getValue(UserTop::getRaitingLabel(), $this->key);
    }

    public function valueFormat()
    {
        if ($this->key == UserTop::TYPE_PLAYTIME) {
            return Servers::getPlayTime($this->value);
        }
        return number_format($this->value, 0, '.', ' ');
    }

    public static function getUserTop($servers, $update = false)
    {
        $cacheKey = 'steam_getUserTop_' . count($servers);
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }
        $top = [];
        foreach ($servers as $server) {
            $sql = "
SELECT id, user_id, `key`, value, server_id, wipe
FROM (
    SELECT id, user_id, `key`, value, server_id, wipe,
           ROW_NUMBER() OVER (PARTITION BY `key`, server_id ORDER BY value DESC) AS rn
    FROM user_top
    WHERE server_id = :server_id
      AND wipe = :wipe
) AS ranked
WHERE rn = 1
ORDER BY server_id, `key`, value DESC;
";

            $userTop = UserTop::findBySql($sql, [
                ':server_id' => $server->id,
                ':wipe' => $server->currentWipe(),
            ])->all();


            $top[$server->id] = [];
            foreach ($userTop as $item) {
                /** @var User $user */
                $user = User::findOne($item['user_id']);
                $top[$server->id][] = [
                    'name' => $item->keyName(),
                    'score' => $item->valueFormat(),
                    'username' => $user->username,
                    'avatar' => $user->getAvatar(),
                ];
            }
        }

        Yii::$app->cache->set($cacheKey, $top, 7*24*60*60);
        return $top;
    }

    /**
     * @param Servers $server
     * @param bool $update
     *
     * @return array|false|mixed
     */
    public static function getUserTops($server, $wipe, $update = false)
    {
        $cacheKey = "User_Top___getUserTops_{$server->id}_{$wipe}";
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            $items = Yii::$app->cache->get($cacheKey);
        }
        if (!isset($items)) {
            $items = [];
            foreach (UserTop::getTopsLabel() as $key => $label) {
                /** @var UserTop[] $userTops */
                $userTops = UserTop::find()
                                   ->alias('ut')
                                   ->joinWith(['user u'])
                                   ->andWhere(['ut.key' => $key])
                                   ->andWhere(['ut.server_id' => $server->id])
                                   ->andWhere(['ut.wipe' => $wipe])
                                   ->andWhere(['u.is_stats' => 1])
                                   ->orderBy(['ut.value' => SORT_DESC])
                                   ->limit(3)
                                   ->all();
                $items[$key] = [
                    'label' => $label,
                    'items' => [],
                ];
                if ($key !== 'playtime') {
                    foreach ($userTops as $position => $item) {
                        $user = $item->user;
                        $color = UserTop::getColor($position);
                        $amount = UserTop::getAmount($position);
                        $items[$key]['items'][] = [
                            'position' => $position,
                            'color' => $color,
                            'amount' => $amount,
                            'steam_id' => $user->steam_id,
                            'score' => $item->valueFormat(),
                            'link' => "/servers/{$server->tag}/{$user->steam_id}",
                            'username' => $user->username,
                            'avatar' => $user->getAvatar(),
                            'status' => $user->getStatus(),
                        ];
                    }
                } else {
                    /** @var Statistics[] $statistics */
                    $statistics = Statistics::find()
                                            ->andWhere(['key' => $key])
                                            ->andWhere(['server_tag' => $server->tag])
                                            ->andWhere(['wipe' => $wipe])
                                            ->orderBy(['value' => SORT_DESC])
                                            ->limit(3)
                                            ->all();
                    foreach ($statistics as $position => $item) {
                        $color = UserTop::getColor($position);
                        $amount = UserTop::getAmount($position);
                        $user = $item->user;
                        $items[$key]['items'][] = [
                            'position' => $position,
                            'color' => $color,
                            'amount' => $amount,
                            'steam_id' => $user->steam_id,
                            'score' => Servers::getPlayTime($item->value),
                            'link' => "/servers/{$server->tag}/{$user->steam_id}",
                            'username' => $user->username,
                            'avatar' => $user->getAvatar(),
                            'status' => $user->getStatus(),
                        ];
                    }
                }
            }
            Yii::$app->cache->set($cacheKey, $items, 7*24*60*60);
        }

        foreach ($items as &$item) {
            $item['label'] = Yii::t('common', $item['label']);
        }
        return $items;
    }

    /**
     * @param       $server
     * @param       $wipe
     * @param false $update
     *
     * @return array|false|mixed
     */
    public static function getAllUserTops($server, $wipe, $update = false)
    {
        $cacheKey = "User_Top_getAllUserTops_{$server->id}_{$wipe}";
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            $items = Yii::$app->cache->get($cacheKey);
        }
        if (empty($items)) {
            $items = [];
            foreach (UserTop::getTopsLabel() as $key => $label) {
                /** @var UserTop[] $userTops */
                $userTops = UserTop::find()
                                   ->andWhere(['key' => $key])
                                   ->andWhere(['server_id' => $server->id])
                                   ->andWhere(['wipe' => $wipe])
                                   ->orderBy(['value' => SORT_DESC])
                                   ->all();
                $items[$key] = [
                    'label' => $label,
                    'items' => [],
                ];
                if ($key !== 'playtime') {
                    foreach ($userTops as $position => $item) {
                        $items[$key]['items'][$item->user->steam_id] = [
                            'position' => $position + 1,
                        ];
                    }
                } else {
                    /** @var Statistics[] $statistics */
                    $statistics = Statistics::find()
                                            ->andWhere(['key' => $key])
                                            ->andWhere(['server_tag' => $server->tag])
                                            ->andWhere(['wipe' => $wipe])
                                            ->orderBy(['value' => SORT_DESC])
                                            ->all();
                    foreach ($statistics as $position => $item) {
                        $items[$key]['items'][$item->steam_id] = [
                            'position' => $position + 1,
                        ];
                    }
                }
            }
            Yii::$app->cache->set($cacheKey, $items, 7*24*60*60);
        }

        foreach ($items as &$item) {
            $item['label'] = Yii::t('common', $item['label']);
        }
        return $items;
    }

    public static function getColor($position) {
        $color = 'gold';
        if ($position === 1) {
            $color = 'silver';
        }
        if ($position === 2) {
            $color = 'bronze';
        }
        return $color;
    }

    public static function getAmount($position) {
        $amount = Yii::$app->settings->get('tops_gold_amount');
        if ($position === 1) {
            $amount = Yii::$app->settings->get('tops_silver_amount');
        }
        if ($position === 2) {
            $amount = Yii::$app->settings->get('tops_bronze_amount');
        }
        return $amount;
    }
}
