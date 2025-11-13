<?php

namespace common\models\map;

use common\models\servers\Servers;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "map_list_vote".
 *
 * @property int $id
 * @property int $map_list_id
 * @property int $server_id
 * @property int $user_id
 * @property string $created_at
 *
 * @property MapList $mapList
 * @property Servers $server
 * @property User $user
 */
class MapListVote extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'map_list_vote';
    }

    public function rules()
    {
        return [
            [['map_list_id', 'server_id', 'user_id'], 'required'],
            [['map_list_id', 'server_id', 'user_id'], 'integer'],
            [['created_at'], 'safe'],
            [['map_list_id', 'server_id', 'user_id'], 'unique', 'targetAttribute' => ['map_list_id', 'server_id', 'user_id'], 'message' => Yii::t('common', 'Вы уже голосовали за эту карту')],
            [['map_list_id'], 'exist', 'skipOnError' => true, 'targetClass' => MapList::class, 'targetAttribute' => ['map_list_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'map_list_id' => 'Map List ID',
            'server_id' => 'Server ID',
            'user_id' => 'User ID',
            'created_at' => 'Created At',
        ];
    }

    public function getMapList()
    {
        return $this->hasOne(MapList::class, ['id' => 'map_list_id']);
    }

    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}

