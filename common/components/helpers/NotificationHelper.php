<?php
namespace common\components\helpers;

use backend\models\Notification;
use common\components\queue\notification\NotificationJob;
use Yii;

/**
 * Helper класс для быстрой отправки уведомлений
 */
class NotificationHelper
{
    /**
     * Отправляет уведомление всем активным пользователям (были на сервере в течение 6 месяцев)
     * 
     * @param string $title Заголовок
     * @param string $message Сообщение (поддерживает HTML)
     * @param string $type Тип уведомления
     * @param string|null $expiresAt Время истечения (например, '+1 week')
     * @param int|null $priority Приоритет (если null, определяется автоматически по типу)
     * @return Notification|false
     */
    public static function sendToAll($title, $message, $type = Notification::TYPE_INFO, $expiresAt = null, $priority = null)
    {
        try {
            // Автоматически определяем приоритет по типу, если не указан
            if ($priority === null) {
                $priority = Notification::getPriorityByType($type);
            }
            
            // Сначала сохраняем в базе данных (только для активных пользователей)
            $notification = Notification::sendToAll($title, $message, $type, $expiresAt, $priority);
            
            // Затем отправляем через WebSocket всем онлайн пользователям
            \console\controllers\ChatServer::sendNotificationToAll($title, $message, $type);
            
            return $notification;
        } catch (\Exception $e) {
            Yii::error("Failed to send notification to all: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Отправляет уведомление конкретному пользователю
     * 
     * @param int $userId ID пользователя
     * @param string $title Заголовок
     * @param string $message Сообщение (поддерживает HTML)
     * @param string $type Тип уведомления
     * @param string|null $expiresAt Время истечения
     * @return Notification|false
     */
    public static function sendToUser($userId, $title, $message, $type = Notification::TYPE_INFO, $expiresAt = null)
    {
        try {
            // Сначала сохраняем в базе данных
            $notification = Notification::sendToUser($userId, $title, $message, $type, $expiresAt);
            
            // Затем отправляем через WebSocket конкретному пользователю
            \console\controllers\ChatServer::sendNotificationToUser($userId, $title, $message, $type);
            
            return $notification;
        } catch (\Exception $e) {
            Yii::error("Failed to send notification to user {$userId}: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Отправляет уведомление модераторам и администраторам
     * 
     * @param string $title Заголовок
     * @param string $message Сообщение (поддерживает HTML)
     * @param string $type Тип уведомления
     * @param string|null $expiresAt Время истечения
     * @return int|false Количество отправленных уведомлений
     */
    public static function sendToModerators($title, $message, $type = Notification::TYPE_INFO, $expiresAt = null)
    {
        try {
            // Получаем всех модераторов и админов
            $moderators = \common\models\user\User::find()
                ->joinWith('userRoles')
                ->where(['user_role.role' => [\common\components\helpers\Role::ROLE_MODERATOR, \common\components\helpers\Role::ROLE_ADMIN, \common\components\helpers\Role::ROLE_SUPPORT]])
                ->andWhere(['>', 'last_visit_server_at', date('Y-m-d H:i:s', time() - (6 * 30 * 24 * 60 * 60))])
                ->all();

            $sentCount = 0;
            foreach ($moderators as $moderator) {
                $notification = Notification::sendToUser($moderator->id, $title, $message, $type, $expiresAt);
                if ($notification) {
                    $sentCount++;
                }
            }

            // Отправляем через WebSocket всем онлайн модераторам
            \console\controllers\ChatServer::sendNotificationToModerators($title, $message, $type);
            
            return $sentCount;
        } catch (\Exception $e) {
            Yii::error("Failed to send notification to moderators: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Отправляет информационное уведомление всем
     */
    public static function info($title, $message, $expiresAt = null)
    {
        return self::sendToAll($title, $message, Notification::TYPE_INFO, $expiresAt);
    }

    /**
     * Отправляет уведомление об успехе всем
     */
    public static function success($title, $message, $expiresAt = null)
    {
        return self::sendToAll($title, $message, Notification::TYPE_SUCCESS, $expiresAt);
    }

    /**
     * Отправляет предупреждение всем
     */
    public static function warning($title, $message, $expiresAt = null)
    {
        return self::sendToAll($title, $message, Notification::TYPE_WARNING, $expiresAt);
    }

    /**
     * Отправляет уведомление об ошибке всем
     */
    public static function error($title, $message, $expiresAt = null)
    {
        return self::sendToAll($title, $message, Notification::TYPE_ERROR, $expiresAt);
    }

    /**
     * Отправляет объявление всем
     */
    public static function announcement($title, $message, $expiresAt = null)
    {
        return self::sendToAll($title, $message, Notification::TYPE_ANNOUNCEMENT, $expiresAt);
    }

    /**
     * Отправляет системное уведомление всем
     */
    public static function system($title, $message, $expiresAt = null)
    {
        return self::sendToAll($title, $message, Notification::TYPE_SYSTEM, $expiresAt);
    }

    /**
     * Отправляет уведомление об акции всем
     */
    public static function promotion($title, $message, $expiresAt = null)
    {
        return self::sendToAll($title, $message, Notification::TYPE_PROMOTION, $expiresAt);
    }

    /**
     * Отправляет уведомление пользователю об успешной покупке
     */
    public static function purchaseSuccess($userId, $itemName, $amount = null)
    {
        $message = "Вы успешно приобрели <strong>{$itemName}</strong>";
        if ($amount) {
            $message .= " на сумму <strong>{$amount}</strong>";
        }
        $message .= ". Предметы доступны в вашем инвентаре на сервере.";
        
        return self::sendToUser($userId, "Покупка завершена", $message, Notification::TYPE_SUCCESS);
    }

    /**
     * Отправляет уведомление о пополнении баланса
     */
    public static function balanceRefilled($userId, $amount)
    {
        $message = "Ваш баланс пополнен на <strong>{$amount}</strong>. Спасибо за пополнение!";
        return self::sendToUser($userId, "Баланс пополнен", $message, Notification::TYPE_SUCCESS);
    }

    /**
     * Отправляет уведомление о вайпе сервера
     */
    public static function serverWipe($serverName, $wipeDate = null)
    {
        $message = "Внимание! Сервер <strong>{$serverName}</strong> будет проходить вайп";
        if ($wipeDate) {
            $message .= " <strong>{$wipeDate}</strong>";
        }
        $message .= ". Сохраните важные предметы!";
        
        return self::sendToAll("Вайп сервера", $message, Notification::TYPE_WARNING, '+1 week');
    }

    /**
     * Отправляет уведомление о технических работах
     */
    public static function maintenance($message, $duration = null)
    {
        $title = "Технические работы";
        if ($duration) {
            $message .= " Ожидаемое время завершения: <strong>{$duration}</strong>.";
        }
        
        return self::sendToAll($title, $message, Notification::TYPE_SYSTEM, '+1 day');
    }

    /**
     * Отправляет уведомление о новой акции
     */
    public static function newPromotion($title, $description, $discount = null)
    {
        $message = $description;
        if ($discount) {
            $message .= " Скидка до <strong>{$discount}%</strong>!";
        }
        $message .= " Не упустите возможность!";
        
        return self::sendToAll($title, $message, Notification::TYPE_PROMOTION, '+1 week');
    }

    /**
     * Отправляет приветственное уведомление новому пользователю
     */
    public static function welcomeUser($userId, $username)
    {
        $message = "Добро пожаловать на наш сервер, <strong>{$username}</strong>! " .
                  "У нас вы найдете множество интересных предметов и акций. " .
                  "Удачной игры!";
        
        return self::sendToUser($userId, "Добро пожаловать!", $message, Notification::TYPE_INFO, '+1 month');
    }

    /**
     * Отправляет уведомление о получении подарка
     */
    public static function giftReceived($userId, $giftName)
    {
        $message = "Вы получили подарок: <strong>{$giftName}</strong>. " .
                  "Проверьте свой инвентарь на сервере!";
        
        return self::sendToUser($userId, "Подарок получен", $message, Notification::TYPE_SUCCESS);
    }

    /**
     * Отправляет уведомление о блокировке аккаунта
     */
    public static function accountBlocked($userId, $reason = null)
    {
        $message = "Ваш аккаунт был заблокирован";
        if ($reason) {
            $message .= " по причине: <strong>{$reason}</strong>";
        }
        $message .= ". Обратитесь в поддержку для выяснения обстоятельств.";
        
        return self::sendToUser($userId, "Аккаунт заблокирован", $message, Notification::TYPE_ERROR);
    }

    /**
     * Отправляет уведомление о разблокировке аккаунта
     */
    public static function accountUnblocked($userId)
    {
        $message = "Ваш аккаунт разблокирован. Добро пожаловать обратно!";
        return self::sendToUser($userId, "Аккаунт разблокирован", $message, Notification::TYPE_SUCCESS);
    }

    /**
     * Отправляет уведомление о новом сервере
     */
    public static function newServer($serverName, $description = null)
    {
        $message = "Добавлен новый сервер: <strong>{$serverName}</strong>";
        if ($description) {
            $message .= ". {$description}";
        }
        $message .= " Присоединяйтесь к игре!";
        
        return self::sendToAll("Новый сервер", $message, Notification::TYPE_ANNOUNCEMENT, '+2 weeks');
    }
}
