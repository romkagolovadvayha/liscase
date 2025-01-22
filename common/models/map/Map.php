<?php

namespace common\models\map;

use common\models\servers\Servers;
use common\models\User;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "map".
 *
 * @property int $id
 * @property string|null $mapId
 * @property string|null $link
 * @property int $seed
 * @property int $size
 * @property int $version
 * @property string|null $image_link
 * @property string|null $image_link_icons
 * @property string|null $created_at
 * @property int $votes
 * @property int $server_id
 * @property bool $is_archive
 *
 * @property-read int $totalVotes
 * @property-read int $userVotes
 *
 * @property Servers[] $servers
 * @property Servers $server
 * @property UserMap[] $userMaps
 */
class Map extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'map';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['seed', 'size', 'version'], 'required'],
            [['seed', 'size', 'version'], 'integer'],
            [['created_at'], 'safe'],
            [['mapId', 'link', 'image_link', 'image_link_icons'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mapId' => 'Map ID',
            'link' => 'Link',
            'seed' => 'Seed',
            'size' => 'Size',
            'version' => 'Version',
            'image_link' => 'Image Link',
            'image_link_icons' => 'Image Link Icons',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Servers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServers()
    {
        return $this->hasMany(Servers::class, ['map_id' => 'id']);
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
     * Gets query for [[UserMaps]] (голоса пользователей).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserMaps()
    {
        return $this->hasMany(UserMap::class, ['map_id' => 'id']);
    }

    /**
     * Считает количество голосов за карту.
     *
     * @return int
     */
    public function getTotalVotes()
    {
        return UserMap::find()->where(['map_id' => $this->id, 'vote' => 1])->count();
    }

    public function voted() {
        $exist = UserMap::find()
            ->andWhere(['user_id' => Yii::$app->user->id])
            ->andWhere(['map_id' => $this->id])
            ->exists();
        if ($exist) {
            return false;
        }

        $userMap = new UserMap();
        $userMap->user_id = Yii::$app->user->id;
        $userMap->map_id = $this->id;
        $userMap->vote = 1;
        $userMap->created_at = date('Y-m-d H:i:s');

        if ($userMap->save()) {
            $this->votes++;
            $this->save();
            return true;
        }

        return false;
    }

    public function unvoted() {
        $vote = UserMap::find()
                        ->andWhere(['user_id' => Yii::$app->user->id])
                        ->andWhere(['map_id' => $this->id])
                        ->one();
        if (empty($vote)) {
            return false;
        }

        $vote->delete();
        $this->votes--;
        $this->save();

        return true;
    }

    /**
     * Считает количество голосов пользователя.
     *
     * @return int
     */
    public function getUserVotes()
    {
        return UserMap::find()->where(['map_id' => $this->id, 'vote' => 1])->count();
    }

    /**
     * Удаляет карту и ее изображения.
     *
     * @return bool|int
     */
    public function archived()
    {
        $this->is_archive = true;
        $this->save();

        if ($this->save()) {
            if (file_exists(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link}")) {
                unlink(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link}");
            }
            if (file_exists(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link_icons}")) {
                unlink(\Yii::getAlias('@frontend/web') . "/uploads/maps/{$this->image_link_icons}");
            }
        }

        return true;
    }

    public function getImage()
    {
        return "/uploads/maps/{$this->image_link_icons}";
    }

    public function getPreviewImage()
    {
        return "/uploads/maps/{$this->image_link}";
    }

    public function renderLike($view)
    {
        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;

        if (!empty($user)) {
            $exist = UserMap::find()->andWhere(['map_id' => $this->id])->andWhere(['user_id' => $user->id])->exists();
        } else {
            $exist = false;
        }

        return $view->render('@frontend/views/maps/like', [
            'model' => $this,
            'liked' => $exist
        ]);
    }
}