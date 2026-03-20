<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "clan_events".
 *
 * @property int $id
 * @property int $clan_id
 * @property int|null $user_id
 * @property string $event_type
 * @property string $description
 * @property string|null $metadata
 * @property int $created_at
 *
 * @property Clan $clan
 * @property User $user
 */
class ClanEvent extends ActiveRecord
{
    const EVENT_MEMBER_JOINED = 'member_joined';
    const EVENT_MEMBER_LEFT = 'member_left';
    const EVENT_MEMBER_KICKED = 'member_kicked';
    const EVENT_MEMBER_PROMOTED = 'member_promoted';
    const EVENT_MEMBER_DEMOTED = 'member_demoted';
    const EVENT_LEADERSHIP_TRANSFERRED = 'leadership_transferred';
    const EVENT_CLAN_CREATED = 'clan_created';
    const EVENT_CLAN_UPDATED = 'clan_updated';
    const EVENT_WAR_DECLARED = 'war_declared';
    const EVENT_WAR_WON = 'war_won';
    const EVENT_WAR_LOST = 'war_lost';
    const EVENT_ACHIEVEMENT_UNLOCKED = 'achievement_unlocked';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_events';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_id', 'event_type', 'description'], 'required'],
            [['clan_id', 'user_id'], 'integer'],
            [['description', 'metadata'], 'string'],
            [['event_type'], 'string', 'max' => 50],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
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
            'clan_id' => Yii::t('common', 'Клан'),
            'user_id' => Yii::t('common', 'Пользователь'),
            'event_type' => Yii::t('common', 'Тип события'),
            'description' => Yii::t('common', 'Описание'),
            'metadata' => Yii::t('common', 'Метаданные'),
            'created_at' => Yii::t('common', 'Дата события'),
        ];
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
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Создание события
     *
     * @param int $clanId
     * @param string $eventType
     * @param string $description
     * @param int|null $userId
     * @param array|null $metadata
     * @return bool
     */
    public static function createEvent($clanId, $eventType, $description, $userId = null, $metadata = null)
    {
        $event = new static();
        $event->clan_id = $clanId;
        $event->event_type = $eventType;
        $event->description = $description;
        $event->user_id = $userId;
        
        if ($metadata !== null) {
            $event->metadata = json_encode($metadata);
        }
        
        return $event->save();
    }

    /**
     * Получение истории клана
     *
     * @param int $clanId
     * @param int $limit
     * @return static[]
     */
    public static function getClanHistory($clanId, $limit = 50)
    {
        return static::find()
            ->where(['clan_id' => $clanId])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Получение метаданных как массива
     *
     * @return array|null
     */
    public function getMetadataArray()
    {
        if ($this->metadata) {
            return json_decode($this->metadata, true);
        }
        return null;
    }
}

