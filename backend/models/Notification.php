<?php
namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * Notification model
 *
 * @property integer $id
 * @property string $title
 * @property string $message
 * @property string $type
 * @property integer $user_id
 * @property integer $is_read
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $expires_at
 */
class Notification extends ActiveRecord
{
    // Виртуальное свойство для формы
    public $target_type;
    
    const TYPE_INFO = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';
    
    const TYPE_ANNOUNCEMENT = 'announcement';
    const TYPE_SYSTEM = 'system';
    const TYPE_PROMOTION = 'promotion';
    const TYPE_SUPPORT = 'support';
    const TYPE_SERVER_WIPE = 'server_wipe';
    const TYPE_MAINTENANCE = 'maintenance';
    
    // Приоритеты уведомлений
    const PRIORITY_LOW = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_HIGH = 3;
    const PRIORITY_URGENT = 4;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'notifications';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title', 'message'], 'required'],
            [['message'], 'string'],
            [['user_id', 'is_read', 'priority', 'created_at', 'updated_at', 'expires_at'], 'integer'],
            [['title'], 'string', 'max' => 255],
            [['type'], 'string', 'max' => 50],
            [['type'], 'default', 'value' => self::TYPE_INFO],
            [['is_read'], 'default', 'value' => 0],
            [['priority'], 'default', 'value' => self::PRIORITY_NORMAL],
            [['user_id'], 'default', 'value' => null], // null = всем пользователям
            [['target_type'], 'safe'], // Виртуальное свойство для формы
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Заголовок',
            'message' => 'Сообщение',
            'type' => 'Тип',
            'user_id' => 'Пользователь',
            'is_read' => 'Прочитано',
            'priority' => 'Приоритет',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
            'expires_at' => 'Истекает',
        ];
    }

    /**
     * Отправляет уведомление всем активным пользователям (были на сервере в течение 6 месяцев)
     */
    public static function sendToAll($title, $message, $type = self::TYPE_INFO, $expiresAt = null, $priority = null)
    {
        $notification = new self();
        $notification->title = $title;
        $notification->message = $message;
        $notification->type = $type;
        $notification->user_id = null; // всем пользователям
        $notification->expires_at = $expiresAt ? strtotime($expiresAt) : null;
        $notification->priority = $priority ?: self::getPriorityByType($type);
        
        if ($notification->save()) {
            // Отправляем через очередь
            Yii::$app->queueProcess->push(new \common\components\queue\notification\NotificationJob([
                'notificationId' => $notification->id,
                'sendToAll' => true,
            ]));
            
            return $notification;
        }
        
        return false;
    }

    /**
     * Отправляет уведомление конкретному пользователю
     */
    public static function sendToUser($userId, $title, $message, $type = self::TYPE_INFO, $expiresAt = null)
    {
        $notification = new self();
        $notification->title = $title;
        $notification->message = $message;
        $notification->type = $type;
        $notification->user_id = $userId;
        $notification->expires_at = $expiresAt ? strtotime($expiresAt) : null;
        
        if ($notification->save()) {
            // Отправляем через очередь
            Yii::$app->queueProcess->push(new \common\components\queue\notification\NotificationJob([
                'notificationId' => $notification->id,
                'sendToAll' => false,
                'userId' => $userId,
            ]));
            
            return $notification;
        }
        
        return false;
    }

    /**
     * Получает непрочитанные уведомления для пользователя
     */
    public static function getUnreadForUser($userId)
    {
        return self::find()
            ->where(['or', ['user_id' => null], ['user_id' => $userId]])
            ->andWhere(['is_read' => 0])
            ->andWhere(['or', ['expires_at' => null], ['>', 'expires_at', time()]])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
    }

    /**
     * Получает все уведомления для пользователя (сортировка по приоритету и дате)
     */
    public static function getAllForUser($userId, $limit = 50)
    {
        return self::find()
            ->where(['or', ['user_id' => null], ['user_id' => $userId]])
            ->andWhere(['or', ['expires_at' => null], ['>', 'expires_at', time()]])
            ->orderBy(['priority' => SORT_DESC, 'created_at' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Помечает уведомление как прочитанное
     */
    public function markAsRead()
    {
        $this->is_read = 1;
        $result = $this->save(false);
        
        if ($result) {
            // Инвалидируем кэш счетчика уведомлений
            $this->invalidateUserNotificationCache($this->user_id);
        }
        
        return $result;
    }

    /**
     * Получает количество непрочитанных уведомлений для пользователя
     */
    public static function getUnreadCount($userId)
    {
        return self::find()
            ->where(['or', ['user_id' => null], ['user_id' => $userId]])
            ->andWhere(['is_read' => 0])
            ->andWhere(['or', ['expires_at' => null], ['>', 'expires_at', time()]])
            ->count();
    }

    /**
     * Помечает все уведомления пользователя как прочитанные
     */
    public static function markAllAsRead($userId)
    {
        $result = self::updateAll(
            ['is_read' => 1],
            ['or', ['user_id' => null], ['user_id' => $userId]]
        );
        
        if ($result) {
            // Инвалидируем кэш счетчика уведомлений
            self::invalidateUserNotificationCache($userId);
        }
        
        return $result;
    }

    /**
     * Удаляет устаревшие уведомления
     */
    public static function cleanExpired()
    {
        return self::deleteAll(['<', 'expires_at', time()]);
    }

    /**
     * Получает иконку для типа уведомления
     */
    public function getIcon()
    {
        switch ($this->type) {
            case self::TYPE_SUCCESS:
                return 'fas fa-check-circle text-success';
            case self::TYPE_WARNING:
                return 'fas fa-exclamation-triangle text-warning';
            case self::TYPE_ERROR:
                return 'fas fa-times-circle text-danger';
            case self::TYPE_ANNOUNCEMENT:
                return 'fas fa-bullhorn text-primary';
            case self::TYPE_SYSTEM:
                return 'fas fa-cog text-info';
            case self::TYPE_PROMOTION:
                return 'fas fa-gift text-warning';
            default:
                return 'fas fa-info-circle text-info';
        }
    }

    /**
     * Инвалидирует кэш счетчика уведомлений для пользователя
     */
    private static function invalidateUserNotificationCache($userId)
    {
        if ($userId) {
            $cacheKey = 'unread_notifications_count_' . $userId;
            Yii::$app->cache->delete($cacheKey);
        }
    }

    /**
     * Получает приоритет по типу уведомления
     */
    public static function getPriorityByType($type)
    {
        switch ($type) {
            case self::TYPE_ERROR:
                return self::PRIORITY_URGENT;
            case self::TYPE_WARNING:
            case self::TYPE_ANNOUNCEMENT:
                return self::PRIORITY_HIGH;
            case self::TYPE_SYSTEM:
            case self::TYPE_SUCCESS:
                return self::PRIORITY_NORMAL;
            case self::TYPE_INFO:
            case self::TYPE_PROMOTION:
            default:
                return self::PRIORITY_LOW;
        }
    }

    /**
     * Получает название приоритета
     */
    public function getPriorityName()
    {
        switch ($this->priority) {
            case self::PRIORITY_URGENT:
                return 'Срочное';
            case self::PRIORITY_HIGH:
                return 'Высокое';
            case self::PRIORITY_NORMAL:
                return 'Обычное';
            case self::PRIORITY_LOW:
                return 'Низкое';
            default:
                return 'Неизвестно';
        }
    }

    /**
     * Получает список типов уведомлений
     */
    public static function getTypeLabels()
    {
        return [
            self::TYPE_INFO => 'Информация',
            self::TYPE_SUCCESS => 'Успех',
            self::TYPE_WARNING => 'Предупреждение',
            self::TYPE_ERROR => 'Ошибка',
            self::TYPE_ANNOUNCEMENT => 'Объявление',
            self::TYPE_SYSTEM => 'Система',
            self::TYPE_PROMOTION => 'Акция',
            self::TYPE_SUPPORT => 'Поддержка',
            self::TYPE_SERVER_WIPE => 'Вайп сервера',
            self::TYPE_MAINTENANCE => 'Техобслуживание',
        ];
    }

    /**
     * Получает список приоритетов уведомлений
     */
    public static function getPriorityLabels()
    {
        return [
            self::PRIORITY_LOW => 'Низкий',
            self::PRIORITY_NORMAL => 'Обычный',
            self::PRIORITY_HIGH => 'Высокий',
            self::PRIORITY_URGENT => 'Критический',
        ];
    }
}
