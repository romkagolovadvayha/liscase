<?php

namespace common\models\user;

use common\models\servers\Servers;
use Yii;

/**
 * This is the model class for table "user_raid".
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $location
 * @property string|null $explosives
 * @property string|null $owners
 * @property int $notify
 * @property string|null $type
 * @property string|null $created_at
 * @property int $server_id
 * @property string|null $wipe
 *
 * @property Servers $server
 * @property User $user
 */
class UserRaid extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_raid';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'server_id'], 'required'],
            [['user_id', 'notify', 'server_id'], 'integer'],
            [['owners'], 'string'],
            [['created_at'], 'safe'],
            [['location', 'explosives', 'wipe'], 'string', 'max' => 255],
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
            'location' => 'Location',
            'explosives' => 'Explosives',
            'owners' => 'Owners',
            'notify' => 'Notify',
            'created_at' => 'Created At',
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

    public static function getTypeName($type) {
        $list = [
            'foundation' => Yii::t('common', 'Фундамент'),
            'wall' => Yii::t('common', 'Стена'),
            'cupboard' => Yii::t('common', 'Шкаф'),
            'door.double.hinged.metal' => Yii::t('common', 'Двойная металическая дверь'),
            'door.double.hinged.wood' => Yii::t('common', 'Двойная деревянная дверь'),
            'door.double.hinged.toptier' => Yii::t('common', 'Двойная мвк дверь'),
            'door.hinged.metal' => Yii::t('common', 'Металическая дверь'),
            'doo.hinged.wood' => Yii::t('common', 'Деревянная дверь'),
            'door.hinged.toptier' => Yii::t('common', 'Мвк дверь'),
            'roof' => Yii::t('common', 'Крыша'),
            'wall.frame' => Yii::t('common', 'Дверной проем'),
            'wall.frame.garagedoor' => Yii::t('common', 'Дверной проем'),
            'gates.external.high.wood' => Yii::t('common', 'Внешние деревянные ворота'),
            'gates.external.high.stone' => Yii::t('common', 'Внешние каменные ворота'),
            'wall.external.high.wood' => Yii::t('common', 'Внешняя деревянная стена'),
            'wall.external.high.stone' => Yii::t('common', 'Внешняя каменная стена'),
        ];

        if (!empty($list[$type])) {
            return $list[$type];
        }

        return null;
    }
}
