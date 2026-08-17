<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use Yii;
use yii\helpers\Inflector;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property int $server_id
 * @property string $status
 * @property string $starts_at
 * @property string $ends_at
 * @property string|null $registration_ends_at
 * @property int|null $max_clans
 * @property int|null $max_participants_per_clan
 * @property string|null $prize_pool_label
 * @property string|null $cover_image
 * @property string|null $format_label
 * @property string|null $tags
 * @property string|null $rules_text
 * @property int $sort
 * @property int $created_at
 * @property int $updated_at
 *
 * @property Servers $server
 * @property TournamentReward[] $rewards
 * @property TournamentRegistration[] $registrations
 * @property TournamentRanking[] $rankings
 */
class Tournament extends ActiveRecord
{
    public const TYPE_CLAN = 'clan';
    public const TYPE_CASH_RACE = 'cash_race';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const PHASE_UPCOMING = 'upcoming';
    public const PHASE_ACTIVE = 'active';
    public const PHASE_PAST = 'past';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tournaments';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['slug', 'title', 'server_id', 'starts_at', 'ends_at'], 'required'],
            [['description', 'rules_text'], 'string'],
            [['server_id', 'max_clans', 'max_participants_per_clan', 'sort'], 'integer'],
            [['max_clans', 'max_participants_per_clan'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
            [['starts_at', 'ends_at', 'registration_ends_at'], 'safe'],
            [['slug'], 'string', 'max' => 128],
            [['slug'], 'unique'],
            [['title'], 'string', 'max' => 255],
            [['prize_pool_label', 'format_label'], 'string', 'max' => 255],
            [['cover_image'], 'string', 'max' => 512],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED]],
            [['type'], 'in', 'range' => [self::TYPE_CLAN, self::TYPE_CASH_RACE]],
            [['type'], 'default', 'value' => self::TYPE_CLAN],
            [['status'], 'default', 'value' => self::STATUS_DRAFT],
            [['sort'], 'default', 'value' => 0],
            [['tags'], 'safe'],
            [['server_id'], 'exist', 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'slug' => Yii::t('common', 'URL (slug)'),
            'title' => Yii::t('common', 'Название'),
            'description' => Yii::t('common', 'Описание'),
            'server_id' => Yii::t('common', 'Сервер'),
            'status' => Yii::t('common', 'Статус'),
            'starts_at' => Yii::t('common', 'Начало'),
            'ends_at' => Yii::t('common', 'Окончание'),
            'registration_ends_at' => Yii::t('common', 'Регистрация до'),
            'max_clans' => Yii::t('common', 'Лимит кланов'),
            'max_participants_per_clan' => Yii::t('common', 'Лимит игроков в составе'),
            'prize_pool_label' => Yii::t('common', 'Призовой фонд (текст)'),
            'cover_image' => Yii::t('common', 'Обложка'),
            'format_label' => Yii::t('common', 'Формат'),
            'tags' => Yii::t('common', 'Теги'),
            'rules_text' => Yii::t('common', 'Правила'),
            'sort' => Yii::t('common', 'Сортировка'),
        ];
    }

    public function beforeValidate()
    {
        if (!parent::beforeValidate()) {
            return false;
        }
        if ($this->slug === '' && $this->title !== '') {
            $this->slug = static::generateSlug($this->title);
        }
        if (is_array($this->tags)) {
            $this->tags = json_encode(array_values($this->tags), JSON_UNESCAPED_UNICODE);
        }
        return true;
    }

    public static function generateSlug(string $title): string
    {
        $base = Inflector::slug($title, '-', false);
        if ($base === '') {
            $base = 'tournament';
        }
        $slug = $base;
        $n = 0;
        while (static::find()->where(['slug' => $slug])->exists()) {
            $n++;
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    public function getRewards()
    {
        return $this->hasMany(TournamentReward::class, ['tournament_id' => 'id'])
            ->orderBy(['place' => SORT_ASC]);
    }

    public function getRegistrations()
    {
        return $this->hasMany(TournamentRegistration::class, ['tournament_id' => 'id']);
    }

    public function getRankings()
    {
        return $this->hasMany(TournamentRanking::class, ['tournament_id' => 'id'])
            ->orderBy(['position' => SORT_ASC]);
    }

    public function getCashRace()
    {
        return $this->hasOne(CashRaceTournament::class, ['tournament_id' => 'id']);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Фаза для фронта: upcoming | active | past.
     */
    public function getPublicPhase(): string
    {
        $now = time();
        $start = strtotime((string)$this->starts_at);
        $end = strtotime((string)$this->ends_at);
        if ($end > 0 && $now > $end) {
            return self::PHASE_PAST;
        }
        if ($start > 0 && $now >= $start && ($end <= 0 || $now <= $end)) {
            return self::PHASE_ACTIVE;
        }
        return self::PHASE_UPCOMING;
    }

    /**
     * Регистрация доступна до окончания турнира (включая активную фазу).
     * Поле registration_ends_at в админке не ограничивает публичную регистрацию.
     */
    public function isRegistrationOpen(): bool
    {
        if (!$this->isPubliclyVisible()) {
            return false;
        }
        if ($this->getPublicPhase() === self::PHASE_PAST) {
            return false;
        }
        $end = strtotime((string)$this->ends_at);
        if ($end > 0) {
            return time() < $end;
        }
        return true;
    }

    public function canAcceptMoreClans(): bool
    {
        if ($this->max_clans === null || (int)$this->max_clans <= 0) {
            return true;
        }
        $count = (int)TournamentRegistration::find()
            ->where(['tournament_id' => (int)$this->id])
            ->count();
        return $count < (int)$this->max_clans;
    }

    public function getRegisteredClansCount(): int
    {
        return (int)TournamentRegistration::find()
            ->where(['tournament_id' => (int)$this->id])
            ->count();
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_DRAFT => Yii::t('common', 'Черновик'),
            self::STATUS_PUBLISHED => Yii::t('common', 'Опубликован'),
            self::STATUS_ARCHIVED => Yii::t('common', 'В архиве'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPhaseList(): array
    {
        return [
            self::PHASE_UPCOMING => Yii::t('common', 'Намечается'),
            self::PHASE_ACTIVE => Yii::t('common', 'Идёт'),
            self::PHASE_PAST => Yii::t('common', 'Прошёл'),
        ];
    }

    public function getPhaseLabel(): string
    {
        return static::getPhaseList()[$this->getPublicPhase()] ?? $this->getPublicPhase();
    }

    public function getStatusLabel(): string
    {
        return static::getStatusList()[$this->status] ?? $this->status;
    }

    /**
     * @return string[]
     */
    public function getTagsArray(): array
    {
        if ($this->tags === null || $this->tags === '') {
            return [];
        }
        if (is_array($this->tags)) {
            return array_values($this->tags);
        }
        $decoded = json_decode((string)$this->tags, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function getCoverUrl(): ?string
    {
        if (!$this->cover_image) {
            return null;
        }
        if (strpos($this->cover_image, 'http://') === 0 || strpos($this->cover_image, 'https://') === 0) {
            return $this->cover_image;
        }
        if (strpos($this->cover_image, 'uploads/') === 0 && Yii::$app->has('s3Api')) {
            return Yii::$app->s3Api->getPublicUrl($this->cover_image);
        }
        return $this->cover_image;
    }

    public static function findPublishedBySlug(string $slug): ?self
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        return static::find()
            ->where(['slug' => $slug, 'status' => self::STATUS_PUBLISHED, 'type' => self::TYPE_CLAN])
            ->with(['server', 'rewards'])
            ->one();
    }
}
