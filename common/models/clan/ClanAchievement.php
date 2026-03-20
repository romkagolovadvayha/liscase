<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use Yii;

/**
 * This is the model class for table "clan_achievements".
 *
 * @property int $id
 * @property int $clan_id
 * @property string $achievement_key
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property int $unlocked_at
 * @property string|null $metadata
 *
 * @property Clan $clan
 */
class ClanAchievement extends ActiveRecord
{
    const ACHIEVEMENT_FIRST_KILL = 'first_kill';
    const ACHIEVEMENT_FIRST_RAID = 'first_raid';
    const ACHIEVEMENT_100_KILLS = '100_kills';
    const ACHIEVEMENT_1000_KILLS = '1000_kills';
    const ACHIEVEMENT_VETERAN_CLAN = 'veteran_clan';
    const ACHIEVEMENT_ELITE_CLAN = 'elite_clan';
    const ACHIEVEMENT_WAR_VICTOR = 'war_victor';
    const ACHIEVEMENT_TOP_CLAN = 'top_clan';
    const ACHIEVEMENT_FULL_ROSTER = 'full_roster';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_achievements';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_id', 'achievement_key', 'name', 'unlocked_at'], 'required'],
            [['clan_id', 'unlocked_at'], 'integer'],
            [['description', 'metadata'], 'string'],
            [['achievement_key'], 'string', 'max' => 50],
            [['name', 'icon'], 'string', 'max' => 255],
            [['clan_id', 'achievement_key'], 'unique', 'targetAttribute' => ['clan_id', 'achievement_key']],
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
            'clan_id' => Yii::t('common', 'Клан'),
            'achievement_key' => Yii::t('common', 'Ключ достижения'),
            'name' => Yii::t('common', 'Название'),
            'description' => Yii::t('common', 'Описание'),
            'icon' => Yii::t('common', 'Иконка'),
            'unlocked_at' => Yii::t('common', 'Дата получения'),
            'metadata' => Yii::t('common', 'Метаданные'),
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
     * Разблокировка достижения
     *
     * @param int $clanId
     * @param string $achievementKey
     * @param array|null $metadata
     * @return static|null
     */
    public static function unlockAchievement($clanId, $achievementKey, $metadata = null)
    {
        // Проверка, не разблокировано ли уже
        $existing = static::findOne(['clan_id' => $clanId, 'achievement_key' => $achievementKey]);
        if ($existing) {
            return $existing;
        }

        $achievementData = self::getDefaultAchievements()[$achievementKey] ?? null;
        if (!$achievementData) {
            return null;
        }

        $achievement = new static();
        $achievement->clan_id = $clanId;
        $achievement->achievement_key = $achievementKey;
        $achievement->name = $achievementData['name'];
        $achievement->description = $achievementData['description'] ?? null;
        $achievement->icon = $achievementData['icon'] ?? null;
        $achievement->unlocked_at = time();
        
        if ($metadata !== null) {
            $achievement->metadata = json_encode($metadata);
        }

        if ($achievement->save()) {
            // Создание события
            ClanEvent::createEvent($clanId, ClanEvent::EVENT_ACHIEVEMENT_UNLOCKED, $achievement->name, null, ['achievement_key' => $achievementKey]);
            return $achievement;
        }

        return null;
    }

    /**
     * Проверка условий и разблокировка достижений
     *
     * @param Clan $clan
     * @return void
     */
    public static function checkAndUnlock($clan)
    {
        $statistics = $clan->getClanStatistics();
        if (!$statistics) {
            return;
        }

        // first_kill - первое убийство клана
        if ($statistics->total_kills >= 1) {
            static::unlockAchievement($clan->id, self::ACHIEVEMENT_FIRST_KILL);
        }

        // 100_kills - 100 убийств клана
        if ($statistics->total_kills >= 100) {
            static::unlockAchievement($clan->id, self::ACHIEVEMENT_100_KILLS);
        }

        // 1000_kills - 1000 убийств клана
        if ($statistics->total_kills >= 1000) {
            static::unlockAchievement($clan->id, self::ACHIEVEMENT_1000_KILLS);
        }

        // veteran_clan - клан существует более 30 дней
        $daysSinceCreation = (time() - $clan->created_at) / (24 * 60 * 60);
        if ($daysSinceCreation >= 30) {
            static::unlockAchievement($clan->id, self::ACHIEVEMENT_VETERAN_CLAN);
        }

        // elite_clan - клан существует более 90 дней
        if ($daysSinceCreation >= 90) {
            static::unlockAchievement($clan->id, self::ACHIEVEMENT_ELITE_CLAN);
        }

        // full_roster - клан достиг максимального количества участников (если есть ограничение)
        $activeMembersCount = $clan->getActiveMembers()->count();
        // Предполагаем максимальное количество участников = 50 (можно настроить)
        if ($activeMembersCount >= 50) {
            static::unlockAchievement($clan->id, self::ACHIEVEMENT_FULL_ROSTER);
        }
    }

    /**
     * Получение предустановленных достижений
     *
     * @return array
     */
    public static function getDefaultAchievements()
    {
        return [
            self::ACHIEVEMENT_FIRST_KILL => [
                'name' => Yii::t('common', 'Первое убийство'),
                'description' => Yii::t('common', 'Клан совершил первое убийство'),
                'icon' => 'fa-solid fa-skull',
            ],
            self::ACHIEVEMENT_FIRST_RAID => [
                'name' => Yii::t('common', 'Первый рейд'),
                'description' => Yii::t('common', 'Клан совершил первый рейд'),
                'icon' => 'fa-solid fa-bomb',
            ],
            self::ACHIEVEMENT_100_KILLS => [
                'name' => Yii::t('common', '100 убийств'),
                'description' => Yii::t('common', 'Клан достиг 100 убийств'),
                'icon' => 'fa-solid fa-trophy',
            ],
            self::ACHIEVEMENT_1000_KILLS => [
                'name' => Yii::t('common', '1000 убийств'),
                'description' => Yii::t('common', 'Клан достиг 1000 убийств'),
                'icon' => 'fa-solid fa-crown',
            ],
            self::ACHIEVEMENT_VETERAN_CLAN => [
                'name' => Yii::t('common', 'Ветеран'),
                'description' => Yii::t('common', 'Клан существует более 30 дней'),
                'icon' => 'fa-solid fa-shield',
            ],
            self::ACHIEVEMENT_ELITE_CLAN => [
                'name' => Yii::t('common', 'Элитный клан'),
                'description' => Yii::t('common', 'Клан существует более 90 дней'),
                'icon' => 'fa-solid fa-star',
            ],
            self::ACHIEVEMENT_WAR_VICTOR => [
                'name' => Yii::t('common', 'Победитель войны'),
                'description' => Yii::t('common', 'Клан выиграл первую войну'),
                'icon' => 'fa-solid fa-sword',
            ],
            self::ACHIEVEMENT_TOP_CLAN => [
                'name' => Yii::t('common', 'Топ-3 клан'),
                'description' => Yii::t('common', 'Клан вошел в топ-3 по статистике'),
                'icon' => 'fa-solid fa-medal',
            ],
            self::ACHIEVEMENT_FULL_ROSTER => [
                'name' => Yii::t('common', 'Полный состав'),
                'description' => Yii::t('common', 'Клан достиг максимального количества участников'),
                'icon' => 'fa-solid fa-users',
            ],
        ];
    }
}

