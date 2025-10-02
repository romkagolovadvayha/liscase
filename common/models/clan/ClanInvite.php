<?php

namespace common\models\clan;

use Yii;
use yii\db\ActiveRecord;
use common\models\user\User;
use common\models\clan\Clan;

/**
 * This is the model class for table "clan_invite".
 *
 * @property int $id
 * @property int|null $user_id ID пользователя, создавшего приглашение
 * @property int|null $clan_id ID клана
 * @property string|null $hash Старый хеш (для совместимости)
 * @property string|null $invite_hash Новый хеш приглашения
 * @property int $status Статус приглашения (1 - активно, 0 - неактивно)
 * @property string|null $created_at Дата создания
 *
 * @property User $user
 * @property Clan $clan
 * @property UserClan[] $userClans
 */
class ClanInvite extends ActiveRecord
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_invite';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'clan_id', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['hash', 'invite_hash'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Создатель приглашения',
            'clan_id' => 'Клан',
            'hash' => 'Хеш',
            'invite_hash' => 'Хеш приглашения',
            'status' => 'Статус',
            'created_at' => 'Дата создания',
        ];
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
     * Gets query for [[Clan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    /**
     * Gets query for [[UserClans]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserClans()
    {
        return $this->hasMany(UserClan::class, ['clan_invite_id' => 'id']);
    }

    /**
     * Создает или получает существующее приглашение для пользователя в клан
     * @param int $clanId ID клана
     * @param int $userId ID пользователя, создающего приглашение
     * @return static
     */
    public static function createOrGetInvite($clanId, $userId)
    {
        $invite = static::find()
            ->andWhere(['clan_id' => $clanId])
            ->andWhere(['user_id' => $userId])
            ->andWhere(['status' => static::STATUS_ACTIVE])
            ->one();

        if (!$invite) {
            $invite = new static();
            $invite->clan_id = $clanId;
            $invite->user_id = $userId;
            $invite->invite_hash = md5(uniqid(mt_rand(), true));
            $invite->status = static::STATUS_ACTIVE;
            $invite->created_at = date('Y-m-d H:i:s');
            $invite->save();
        }

        return $invite;
    }

    /**
     * Находит приглашение по хешу
     * @param string $hash
     * @return static|null
     */
    public static function findByHash($hash)
    {
        return static::find()
            ->andWhere(['invite_hash' => $hash])
            ->andWhere(['status' => static::STATUS_ACTIVE])
            ->one();
    }

    /**
     * Генерирует ссылку на приглашение
     * @param string $serverTag
     * @return string
     */
    public function getInviteLink()
    {
        return Yii::$app->urlManager->createAbsoluteUrl([
            '/clans/accept-invite',
            'inviteHash' => $this->invite_hash
        ]);
    }

    /**
     * Получает статистику приглашения
     * @return array
     */
    public function getInviteStats()
    {
        $totalInvited = $this->getUserClans()->count();
        $recentInvited = $this->getUserClans()
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-7 days'))])
            ->count();

        return [
            'total_invited' => $totalInvited,
            'recent_invited' => $recentInvited,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert && empty($this->created_at)) {
                $this->created_at = date('Y-m-d H:i:s');
            }
            return true;
        }
        return false;
    }
}