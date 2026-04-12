<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use common\models\user\User;
use Yii;
use yii\helpers\Url;
use yii\web\UploadedFile;

/**
 * This is the model class for table "clans".
 *
 * @property int $id
 * @property string $name
 * @property string $tag
 * @property int $leader_user_id
 * @property int $server_id
 * @property string|null $motto
 * @property string|null $logo
 * @property string $privacy
 * @property string|null $description
 * @property int $level
 * @property int $experience
 * @property int $created_at
 * @property int $updated_at
 * @property string $color_tag HEX для тега в чате игры (белый список пресетов)
 *
 * @property User $leaderUser
 * @property Servers $server
 * @property ClanMember[] $members
 * @property ClanStatistics[] $statistics
 * @property-read int $activeMembersCount активные участники (из подзапроса ClanSearch или COUNT по связи)
 */
class Clan extends ActiveRecord
{
    const PRIVACY_OPEN = 'open';
    const PRIVACY_CLOSED = 'closed';
    const PRIVACY_INVITE_ONLY = 'invite_only';

    /** Допустимые цвета тега (сайт + API + плагин) */
    public const TAG_COLOR_PRESETS = [
        '#5DCEA4',
        '#6EB5FF',
        '#E8C547',
        '#F08C6B',
        '#B794F4',
        '#EF4444',
        '#34D399',
        '#22D3EE',
        '#E879F9',
        '#FB923C',
    ];

    public const DEFAULT_TAG_COLOR = '#5DCEA4';

    /** @var int|null значение из SELECT в ClanSearch; null — считать через [[getActiveMembers]] */
    private $_activeMembersCount = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clans';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'tag', 'leader_user_id', 'server_id'], 'required'],
            [['leader_user_id', 'server_id', 'level', 'experience'], 'integer'],
            [['motto', 'description'], 'string'],
            [['name'], 'string', 'max' => 255],
            [['tag'], 'string', 'max' => 8],
            [['color_tag'], 'string', 'max' => 20],
            [['color_tag'], 'in', 'range' => self::TAG_COLOR_PRESETS, 'skipOnEmpty' => true],
            [['color_tag'], 'default', 'value' => self::DEFAULT_TAG_COLOR],
            [['logo'], 'string', 'max' => 255],
            [['privacy'], 'in', 'range' => [self::PRIVACY_OPEN, self::PRIVACY_CLOSED, self::PRIVACY_INVITE_ONLY]],
            [['privacy'], 'default', 'value' => self::PRIVACY_OPEN],
            [['level'], 'default', 'value' => 1],
            [['experience'], 'default', 'value' => 0],
            [['name', 'tag'], 'unique', 'targetAttribute' => ['name', 'server_id'], 'message' => Yii::t('common', 'Клан с таким названием уже существует на этом сервере')],
            [['tag', 'server_id'], 'unique', 'targetAttribute' => ['tag', 'server_id'], 'message' => Yii::t('common', 'Клан с таким тегом уже существует на этом сервере')],
            [['leader_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['leader_user_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => Yii::t('common', 'Название'),
            'tag' => Yii::t('common', 'Тег'),
            'color_tag' => Yii::t('common', 'Цвет тега'),
            'leader_user_id' => Yii::t('common', 'Лидер'),
            'server_id' => Yii::t('common', 'Сервер'),
            'motto' => Yii::t('common', 'Девиз'),
            'logo' => Yii::t('common', 'Логотип'),
            'privacy' => Yii::t('common', 'Приватность'),
            'description' => Yii::t('common', 'Описание'),
            'level' => Yii::t('common', 'Уровень'),
            'experience' => Yii::t('common', 'Опыт'),
            'created_at' => Yii::t('common', 'Дата создания'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
        ];
    }

    /**
     * Gets query for [[LeaderUser]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getLeaderUser()
    {
        return $this->hasOne(User::class, ['id' => 'leader_user_id']);
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
     * Gets query for [[Members]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMembers()
    {
        return $this->hasMany(ClanMember::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ActiveMembers]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getActiveMembers()
    {
        return $this->hasMany(ClanMember::class, ['clan_id' => 'id'])
            ->andWhere(['IS', 'leave_date', null]);
    }

    /**
     * Число активных участников: из подзапроса в [[\backend\models\ClanSearch]] или COUNT по связи.
     */
    public function getActiveMembersCount(): int
    {
        if ($this->_activeMembersCount !== null) {
            return (int) $this->_activeMembersCount;
        }

        return (int) $this->getActiveMembers()->count();
    }

    /**
     * Вызывается при подстановке строки из БД (подзапрос в ClanSearch).
     *
     * @param int|string|null $value
     */
    public function setActiveMembersCount($value): void
    {
        $this->_activeMembersCount = $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Gets query for [[Statistics]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getStatistics()
    {
        return $this->hasMany(ClanStatistics::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[Events]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getEvents()
    {
        return $this->hasMany(ClanEvent::class, ['clan_id' => 'id'])
            ->orderBy(['created_at' => SORT_DESC]);
    }

    /**
     * Gets query for [[Achievements]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAchievements()
    {
        return $this->hasMany(ClanAchievement::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanRankings]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanRankings()
    {
        return $this->hasMany(ClanRanking::class, ['clan_id' => 'id']);
    }

    /**
     * Получение URL логотипа
     *
     * @return string
     */
    public function getLogoUrl()
    {
        if (!$this->logo) {
            return '/images/default-clan-logo.png';
        }
        if (strpos($this->logo, 'http://') === 0 || strpos($this->logo, 'https://') === 0) {
            return $this->logo;
        }
        // Ключ в S3 (uploads/clans/...)
        if (strpos($this->logo, 'uploads/') === 0 && Yii::$app->has('s3Api')) {
            return Yii::$app->s3Api->getPublicUrl($this->logo);
        }
        if (file_exists(Yii::getAlias('@frontend/web') . $this->logo)) {
            return $this->logo;
        }

        return '/images/default-clan-logo.png';
    }

    /**
     * Удаление логотипа
     *
     * @return bool
     */
    public function deleteLogo()
    {
        if ($this->logo) {
            $logoPath = Yii::getAlias('@frontend/web') . $this->logo;
            if (file_exists($logoPath)) {
                @unlink($logoPath);
            }
            $this->logo = null;
            return $this->save(false);
        }
        return true;
    }

    /**
     * Получение текстового названия типа приватности
     *
     * @return string
     */
    public function getPrivacyLabel()
    {
        $labels = [
            self::PRIVACY_OPEN => Yii::t('common', 'Открытый'),
            self::PRIVACY_CLOSED => Yii::t('common', 'Закрытый'),
            self::PRIVACY_INVITE_ONLY => Yii::t('common', 'По приглашению'),
        ];
        return $labels[$this->privacy] ?? $this->privacy;
    }

    /**
     * Проверка, открыт ли клан для вступления
     *
     * @return bool
     */
    public function isOpen()
    {
        return $this->privacy === self::PRIVACY_OPEN;
    }

    /**
     * Проверка, закрыт ли клан
     *
     * @return bool
     */
    public function isClosed()
    {
        return $this->privacy === self::PRIVACY_CLOSED;
    }

    /**
     * Проверка, только ли по приглашению
     *
     * @return bool
     */
    public function isInviteOnly()
    {
        return $this->privacy === self::PRIVACY_INVITE_ONLY;
    }

    /**
     * Добавление события в историю
     *
     * @param string $eventType
     * @param string $description
     * @param int|null $userId
     * @param array|null $metadata
     * @return bool
     */
    public function addEvent($eventType, $description, $userId = null, $metadata = null)
    {
        return ClanEvent::createEvent($this->id, $eventType, $description, $userId, $metadata);
    }

    /**
     * Проверка и разблокировка достижений
     *
     * @return void
     */
    public function checkAchievements()
    {
        ClanAchievement::checkAndUnlock($this);
    }

    /**
     * Получение рейтингов клана
     *
     * @param int|null $serverId
     * @param string $period
     * @return array
     */
    public function getRankings($serverId = null, $period = 'all_time')
    {
        $query = ClanRanking::find()
            ->where(['clan_id' => $this->id, 'period' => $period]);
        
        if ($serverId) {
            $query->andWhere(['server_id' => $serverId]);
        }
        
        return $query->all();
    }

    /**
     * Получение ссылки на клан
     *
     * @return string
     */
    public function getLink()
    {
        $serverTag = $this->server ? $this->server->tag : 'default';
        return Url::to(['/clans/view', 'serverTag' => $serverTag, 'id' => $this->id]);
    }

    /**
     * Добавление участника
     *
     * @param int $userId
     * @param string $role
     * @return ClanMember|null
     */
    /**
     * @param array|null $joinEventMetadata опционально в JSON события member_joined (например invite_link_id)
     */
    public function addMember($userId, $role = ClanMember::ROLE_MEMBER, $joinEventMetadata = null)
    {
        // Проверка, не является ли пользователь уже активным участником
        $existingMember = ClanMember::find()
            ->where(['clan_id' => $this->id, 'user_id' => $userId])
            ->andWhere(['IS', 'leave_date', null])
            ->one();
        
        if ($existingMember) {
            return null;
        }

        $member = new ClanMember();
        $member->clan_id = $this->id;
        $member->user_id = $userId;
        $member->role = $role;
        $member->join_date = date('Y-m-d H:i:s');
        
        if ($member->save()) {
            // Назначение всех разрешений лидеру
            if ($role === ClanMember::ROLE_LEADER) {
                $permissions = ClanPermission::getDefaultPermissions();
                foreach ($permissions as $permission) {
                    $member->addPermission($permission->key);
                }
            }

            $server = $this->server ?: Servers::findOne($this->server_id);
            if ($server) {
                $wipe = $server->currentWipe();
                ClanMemberStatsBaseline::captureBaseline($member, $this->server_id, $wipe);
            }
            
            // Создание события
            $this->addEvent(
                'member_joined',
                Yii::t('common', 'Пользователь {username} вступил в клан', ['username' => $member->user->username]),
                $userId,
                $joinEventMetadata
            );

            // GET …/clans/list отдаёт update_at с клана; плагин ClanManager сравнивает его и иначе не подхватывает новый состав.
            $this->updateAttributes(['updated_at' => time()]);

            return $member;
        }
        
        return null;
    }

    /**
     * Удаление участника
     *
     * @param int $userId
     * @return bool
     */
    public function removeMember($userId)
    {
        $member = ClanMember::find()
            ->where(['clan_id' => $this->id, 'user_id' => $userId])
            ->andWhere(['IS', 'leave_date', null])
            ->one();
        
        if ($member) {
            $member->leave_date = date('Y-m-d H:i:s');
            if ($member->save()) {
                $member->clearAuthEntityPermissions();
                $server = $this->server ?: Servers::findOne($this->server_id);
                if ($server) {
                    $wipe = $server->currentWipe();
                    ClanMemberStatistics::finalizeAndFreeze($member, $this->server_id, $wipe);
                }
                $this->addEvent('member_left', Yii::t('common', 'Пользователь {username} покинул клан', ['username' => $member->user->username]), $userId);
                // Иначе плагин не видит смену списка users (см. GamePluginClanListBuilder + ClanManager update_at).
                $this->updateAttributes(['updated_at' => time()]);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Передача лидерства
     *
     * @param int $newLeaderId
     * @return bool
     */
    public function transferLeadership($newLeaderId)
    {
        $oldLeader = ClanMember::find()
            ->where(['clan_id' => $this->id, 'user_id' => $this->leader_user_id])
            ->andWhere(['IS', 'leave_date', null])
            ->one();
        
        $newLeader = ClanMember::find()
            ->where(['clan_id' => $this->id, 'user_id' => $newLeaderId])
            ->andWhere(['IS', 'leave_date', null])
            ->one();
        
        if (!$oldLeader || !$newLeader) {
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Старый лидер становится обычным участником (офицерский ранг больше не используется)
            $oldLeader->role = ClanMember::ROLE_MEMBER;
            $oldLeader->save(false);
            
            // Новый лидер
            $newLeader->role = ClanMember::ROLE_LEADER;
            $newLeader->save(false);
            
            // Обновление клана
            $this->leader_user_id = $newLeaderId;
            $this->save(false);
            
            // Назначение всех разрешений новому лидеру
            $permissions = ClanPermission::getDefaultPermissions();
            foreach ($permissions as $permission) {
                $newLeader->addPermission($permission->key);
            }
            
            // Создание события
            $this->addEvent('leadership_transferred', Yii::t('common', 'Лидерство передано от {oldLeader} к {newLeader}', [
                'oldLeader' => $oldLeader->user->username,
                'newLeader' => $newLeader->user->username
            ]), $newLeaderId);
            
            $transaction->commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * Получение статистики клана
     *
     * @param string|null $wipe
     * @return ClanStatistics|null
     */
    public function getClanStatistics($wipe = null)
    {
        $query = ClanStatistics::find()
            ->where(['clan_id' => $this->id, 'server_id' => $this->server_id]);
        
        if ($wipe) {
            $query->andWhere(['wipe' => $wipe]);
        } else {
            // Получаем текущий вайп сервера
            if ($this->server) {
                $wipe = $this->server->currentWipe();
                $query->andWhere(['wipe' => $wipe]);
            }
        }
        
        return $query->one();
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        if ($insert) {
            // Создание события при создании клана
            $this->addEvent('clan_created', Yii::t('common', 'Клан {name} создан', ['name' => $this->name]), $this->leader_user_id);
            
            // Добавление лидера как участника
            $this->addMember($this->leader_user_id, ClanMember::ROLE_LEADER);
        }
    }
}

