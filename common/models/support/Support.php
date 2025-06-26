<?php

namespace common\models\support;

use common\models\servers\Servers;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "support".
 *
 * @property int $id
 * @property int $user_id
 * @property int $suspect_user_id
 * @property string $server_tag
 * @property int $status
 * @property string|null $updated_at
 * @property string|null $created_at
 * @property bool $is_bot
 *
 * @property SupportMessage[] $supportMessages
 * @property User $user
 * @property User $suspectUser
 * @property Servers $server
 */
class Support extends \yii\db\ActiveRecord
{
    public const STATUS_OPEN = 1;
    public const STATUS_CLOSED = 2;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_OPEN      => Yii::t('common', 'Открыт'),
            self::STATUS_CLOSED       => Yii::t('common', 'Закрыт'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'support';
    }

    public function getNumber() {
        return $this->id + 43242;
    }

    /**
     * @param $number
     *
     * @return Support|null
     */
    public static function findByNumber($number) {
        return self::findOne($number - 43242);
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'status'], 'required'],
            [['user_id', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['server_tag'], 'string', 'max' => 11],
            [['server_tag'], 'exist', 'skipOnEmpty' => true, 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_tag' => 'tag']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['suspect_user_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => Yii::t('common', 'Автор'),
            'status' => Yii::t('common', 'Статус'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
            'created_at' => Yii::t('common', 'Дата создания'),
            'suspect_user_id' => Yii::t('common', 'Подозреваемый'),
            'server_tag' => Yii::t('common', 'Сервер'),
        ];
    }

    /**
     * Gets query for [[SupportMessages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSupportMessages()
    {
        return $this->hasMany(SupportMessage::class, ['support_id' => 'id']);
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
     * Gets query for [[SuspectUser]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSuspectUser()
    {
        return $this->hasOne(User::class, ['id' => 'suspect_user_id']);
    }

    /**
     * Gets query for [[Servers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['tag' => 'server_tag']);
    }

    public function getUrl($key = 'ticket') {
        if ($key === 'ticket') {
            if (empty($this->id)) {
                return "/support";
            }
            return "/support/ticket?id={$this->getNumber()}";
        }
        if ($key === 'close') {
            return "/support/ticket-close?id={$this->getNumber()}";
        }
        if ($key === 'open') {
            return "/support/ticket-open?id={$this->getNumber()}";
        }

        return null;
    }

    public function unread($userId) {
        return SupportRead::find()
            ->andWhere(['support_id' => $this->id])
            ->andWhere(['user_id' => $userId])
            ->andWhere(['status' => SupportRead::STATUS_UNREAD])
            ->count();
    }

    public static function unreadAll($userId) {
        return SupportRead::find()
            ->andWhere(['user_id' => $userId])
            ->andWhere(['status' => SupportRead::STATUS_UNREAD])
            ->count();
    }
}
