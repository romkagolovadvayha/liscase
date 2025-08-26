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
 * @property string      $server_skin_category_id
 * @property int         $likes
 * @property int         $creator_user_id
 * @property string|null $created_at
 *
 * @property ServerSkinLike[] $serverSkinLikes
 * @property User $user
 * @property User $creatorUser
 * @property ServerSkinCategory $serverSkinCategory
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
            'skin_id' => 'Skin ID',
            'name' => Yii::t('common', 'Название'),
            'status' => Yii::t('common', 'Статус'),
            'created_at' => Yii::t('common', 'Дата создания'),
        ];
    }

    public function getImagePubUrl($cdn = true) {
        if ($cdn) {
            return Yii::$app->settings->get('site_cdnUrl') . $this->image;
        }
        return $this->image;
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

    /**
     * Gets query for [[ServerSkinCategory]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServerSkinCategory()
    {
        return $this->hasOne(ServerSkinCategory::class, ['id' => 'server_skin_category_id']);
    }

    /**
     * Gets query for [[CreatorUser]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCreatorUser()
    {
        return $this->hasOne(User::class, ['id' => 'creator_user_id']);
    }

    public function passed($time_format = 'H:i', $month_format = 'd.m.Y', $year_format = 'd.m.Y') {
        return DateHelper::passed($this->created_at);
    }

    public function getLink() {
        return '/skins/view?id=' . $this->id;
    }

    public static function getInfoSkin($publishedFileId) {
        $postData = [
            'itemcount' => 1,
            'publishedfileids[0]' => $publishedFileId,
        ];

        $response = Yii::$app->curl->setPostParams($postData)
                                   ->post('https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/');

        $data = json_decode($response, true);
        return $data['response']['publishedfiledetails'][0] ?? null;
    }
}
