<?php

namespace common\models\user;

use common\models\servers\Servers;
use Yii;

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
    public static function updateTop($user, $param, $value, $server, $wipeDate) {
        foreach (self::getRaiting() as $key => $item) {
            if (empty($item[$param])) {
                continue;
            }
            $amount = $item[$param] * $value;
            UserTop::setTop($user, $key, $amount, $server, $wipeDate);
        }
    }

    /**
     * @param User $user
     * @param string $key
     * @param float $value
     * @param Servers $server
     * @param string $wipeDate
     */
    public static function setTop($user, $key, $value, $server, $wipeDate) {
        /** @var UserTop $userTop */
        $userTop = UserTop::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['key' => $key])
            ->andWhere(['server_id' => $server->id])
            ->andWhere(['wipe' => $wipeDate])
            ->one();

        if (!empty($userTop)) {
            $userTop->value += $value;
            $userTop->save();
            return;
        }

        $userTop = new UserTop();
        $userTop->user_id = $user->id;
        $userTop->key = $key;
        $userTop->value = $value;
        $userTop->server_id = $server->id;
        $userTop->wipe = $wipeDate;
        $userTop->save();
    }

    public static function getRaiting() {
        return [
          'reider' => [
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
          'kills' => [
            'kills' => 1,
          ],
          'scientists' => [
            'scientists' => 1,
          ],
          'deaths' => [
            'deaths' => 1,
          ],
          'playtime' => [
            'playtime' => 1,
          ],
          'farmer' => [
            'wood' => 0.05,
            'stones' => 0.3,
            'metal.ore' => 0.5,
            'sulfur.ore' => 1,
          ],
          'fishing' => [
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
          'hunter' => [
              'chicken' => 1,
              'bear' => 1,
              'boar' => 1,
              'polarbear' => 1,
              'deer' => 1,
              'horse' => 1,
              'wolf2' => 1,
              'wolf' => 1,
          ],
          'fermer' => [
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
          ],
        ];
    }
}
