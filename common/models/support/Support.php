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
     * Публичный номер тикета в URL/API: {@see getNumber()} = id + NUMBER_OFFSET.
     * Единственный источник смещения для findByNumber / маршрутов.
     */
    public const NUMBER_OFFSET = 43242;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        $cacheKey = 'support_status_list';
        $cached = Yii::$app->cache->get($cacheKey);
        
        if ($cached === false) {
            $cached = [
                self::STATUS_OPEN      => Yii::t('common', 'Открыт'),
                self::STATUS_CLOSED       => Yii::t('common', 'Закрыт'),
            ];
            // Кэшируем на 24 часа (86400 секунд)
            Yii::$app->cache->set($cacheKey, $cached, 86400);
        }
        
        return $cached;
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'support';
    }

    public function getNumber(): int
    {
        return (int) $this->id + self::NUMBER_OFFSET;
    }

    /**
     * Первичный ключ по публичному номеру (значение getNumber() / поля number в API).
     */
    public static function primaryKeyFromPublicNumber(int $publicNumber): ?int
    {
        if ($publicNumber <= self::NUMBER_OFFSET) {
            return null;
        }
        return $publicNumber - self::NUMBER_OFFSET;
    }

    /**
     * Поиск по публичному номеру тикета (не по PK в БД).
     *
     * @param int|string $number значение из getNumber()
     * @return Support|null
     */
    public static function findByNumber($number)
    {
        $pk = self::primaryKeyFromPublicNumber((int) $number);
        if ($pk === null || $pk < 1) {
            return null;
        }
        return self::findOne($pk);
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

    /**
     * Количество непрочитанных по списку user_id одним запросом. Возвращает [user_id => count].
     */
    public static function unreadAllBatch(array $userIds) {
        if (empty($userIds)) {
            return [];
        }
        $userIds = array_unique(array_map('intval', $userIds));
        $rows = SupportRead::find()
            ->select(['user_id', 'COUNT(*) as cnt'])
            ->andWhere(['user_id' => $userIds])
            ->andWhere(['status' => SupportRead::STATUS_UNREAD])
            ->groupBy('user_id')
            ->asArray()
            ->all();
        $result = array_fill_keys($userIds, 0);
        foreach ($rows as $row) {
            $result[(int)$row['user_id']] = (int)$row['cnt'];
        }
        return $result;
    }
}
