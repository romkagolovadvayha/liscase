<?php

namespace common\models\serverskin;

use common\components\helpers\DateHelper;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "server_skin".
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $skin_id
 * @property string|null $name
 * @property int         $status
 * @property string      $image
 * @property int         $likes
 * @property string|null $created_at
 *
 * @property ServerSkinLike[] $serverSkinLikes
 * @property User $user
 */
class ServerSkin extends \yii\db\ActiveRecord
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_REJECT = 2;
    public const STATUS_WAIT = 3;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'server_skin';
    }

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_REJECT       => Yii::t('common', 'Отклонен'),
            self::STATUS_ACTIVE      => Yii::t('common', 'Активен'),
            self::STATUS_WAIT      => Yii::t('common', 'На модерации'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'status', 'name', 'skin_id'], 'required'],
            [['user_id', 'status', 'likes', 'skin_id'], 'integer'],
            [['created_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['name'], 'filter', 'filter' => '\yii\helpers\HtmlPurifier::process'],
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
            'name' => Yii::t('common', 'Название'),
            'status' => Yii::t('common', 'Статус'),
            'created_at' => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * Gets query for [[ServerSkinLikes]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServerSkinLikes()
    {
        return $this->hasMany(ServerSkinLike::class, ['server_skin_id' => 'id']);
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

    public function passed($time_format = 'H:i', $month_format = 'd.m.Y', $year_format = 'd.m.Y') {
        return DateHelper::passed($this->created_at);
    }

    public function getLink() {
        return '/skins/view?id=' . $this->id;
    }
}
