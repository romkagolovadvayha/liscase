<?php

namespace backend\models;

use common\components\queue\telegram\SendMessageJob;
use common\components\queue\telegram\SendPhotoJob;
use common\components\queue\telegram\TelegramJob;
use common\components\queue\telegram\TelegramMassJob;
use common\components\queue\vk\SendVkMessageJob;
use common\components\telegram\TelegramPersonalBot;
use common\components\vk\VkApiHelper;
use common\components\helpers\Role;
use common\models\vk\VkUser;
use common\models\country\Country;
use common\models\country\CountryPromo;
use common\models\credit\Credit;
use common\models\invoice\Invoice;
use common\models\package\Marathon;
use common\models\profit\Profit;
use common\models\user\User;
use common\models\user\UserBalance;
use common\models\user\UserBlogger;
use common\models\user\UserSocialNetwork;
use common\models\userInvestor\UserInvestor;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

/**
 * This is the model class for table "telegram_constructor".
 *
 * @property int $id
 * @property string $title
 * @property int $audience_id
 * @property int $bot_id
 * @property int $telegram_constructor_message_id
 * @property int $status
 * @property bool $only_with_user Отправлять только пользователям с привязанным user (для VK)
 * @property string|null $created_at
 *
 * @property TelegramConstructorMessage $telegramConstructorMessage
 */
class TelegramConstructor extends \yii\db\ActiveRecord
{

    public const STATUS_NEW = 1;
    public const STATUS_IN_PROGRESS = 2;
    public const STATUS_SUCCESS = 3;
    public const STATUS_ERROR = 4;

    public const PERSONAL_BOT = 1;
    public const VK_GROUP = 2;
    public const OTHER_BOT = 3;

    public const AUDIENCE_TEST = 1;
    public const AUDIENCE_ALL = 2;
    public const AUDIENCE_WINNER = 3;
    public const AUDIENCE_MODERATORS = 4;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'telegram_constructor';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['bot_id', 'telegram_constructor_message_id', 'title', 'audience_id'], 'required'],
            [['bot_id', 'status', 'telegram_constructor_message_id', 'audience_id'], 'integer'],
            [['only_with_user'], 'boolean'],
            [['created_at', 'status'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Название рассылки',
            'audience_id' => 'Аудитория',
            'bot_id' => 'Платформа',
            'status' => 'Статус',
            'telegram_constructor_message_id' => 'Сообщение',
            'only_with_user' => 'Только для пользователей с привязанным user',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * @throws \Exception
     */
    public function saveRecord(): bool
    {
        try {
            $this->status = self::STATUS_NEW;
            $this->created_at = date('Y-m-d H:i:s');
            
            // Приводим к int для корректного сохранения
            $this->bot_id = (int)$this->bot_id;
            $this->audience_id = (int)$this->audience_id;
            $this->telegram_constructor_message_id = (int)$this->telegram_constructor_message_id;
            
            if (!$this->save(false)) {
                \Yii::error("TelegramConstructor save failed: " . json_encode($this->errors, JSON_UNESCAPED_UNICODE), __METHOD__);
                return false;
            }
        } catch (\Exception $e) {
            \Yii::error("TelegramConstructor save exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            $this->addError('id', 'Ошибка сохранения: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @return string[]
     */
    public static function getBotList(): array
    {
        return [
            self::PERSONAL_BOT => 'Telegram: Персональный бот',
            self::VK_GROUP => 'ВКонтакте: Группа',
//            self::OTHER_BOT => 'Other Bot'
        ];
    }

    /**
     * @return string[]
     */
    public static function getAudienceList(): array
    {
        return [
            self::AUDIENCE_TEST => 'Тестовая аудитория',
            self::AUDIENCE_ALL => 'Все пользователи',
            self::AUDIENCE_WINNER => 'Победители',
            self::AUDIENCE_MODERATORS => 'Модераторы и админы',
        ];
    }

    /**
     * @return string[]
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_NEW => 'Создан',
            self::STATUS_IN_PROGRESS => 'В процессе',
            self::STATUS_SUCCESS => 'Завершен',
            self::STATUS_ERROR => 'Ошибка'
        ];
    }

    /**
     * Универсальный метод отправки рассылки
     * @return bool
     */
    public function send()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        switch ($this->bot_id) {
            case self::PERSONAL_BOT:
                return $this->sendPersonalBot();
            case self::VK_GROUP:
                return $this->sendVkGroup();
            default:
                Yii::error("Unknown bot_id: {$this->bot_id}", __METHOD__);
                return false;
        }
    }

    /**
     * Отправка в Telegram персональный бот
     * @return bool|void
     */
    public function sendPersonalBot()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        foreach (self::getAudience($this->audience_id, self::PERSONAL_BOT) as $userId) {
            /** @var User $user */
            $user = User::findOne($userId);
            if (empty($user) || empty($user->telegram_chat_id)) {
                continue;
            }
            
            // Для каждого пользователя нужна своя ссылка (с подстановкой user_id)
            // Поэтому не кэшируем photo, если это ссылка с плейсхолдером
            $imageLink = $this->telegramConstructorMessage->getImageLink($user->current_language);
            $isDynamicLink = !empty($imageLink) && strpos($imageLink, '@') === 0 && strpos($imageLink, '{user_id}') !== false;
            
            $cacheKey = "sendPersonalBot_{$this->telegramConstructorMessage->id}_{$user->current_language}";
            $cacheData = Yii::$app->cache->get($cacheKey);
            
            if (!empty($cacheData) && !$isDynamicLink) {
                $message = $cacheData['message'];
                $photo = $cacheData['photo'];
                $buttons = $cacheData['buttons'];
            } else {
                $buttons = $this->telegramConstructorMessage->getTelegramButtons($user->current_language);
                $message = $this->telegramConstructorMessage->getTelegramMessage($user->current_language, !empty($buttons));
                $photo = null;
                if (!empty($imageLink)) {
                    // Передаем user_id для подстановки в ссылку
                    $photo = $this->telegramConstructorMessage->getPubUrl('', $user->current_language, $user->id);
                }
                
                // Кэшируем только если ссылка не динамическая
                if (!$isDynamicLink) {
                    Yii::$app->cache->set($cacheKey, [
                        'message' => $message,
                        'photo' => $photo,
                        'buttons' => $buttons
                    ], 60);
                }
            }
            if (!empty($buttons) || empty($photo)) {
                Yii::$app->queueTelegram->push(new SendMessageJob([
                                                                      'telegram_chat_id' => $user->telegram_chat_id,
                                                                      'message' => $message,
                                                                      'buttons' => $buttons,
                ]));
            } else {
                Yii::$app->queueTelegram->push(new SendPhotoJob([
                                                                      'telegram_chat_id' => $user->telegram_chat_id,
                                                                      'photo' => $photo,
                                                                      'message' => $message,
                                                                  ]));
            }
        }

        return true;
    }

    /**
     * Отправка в личные сообщения участников группы ВКонтакте
     * @return bool
     */
    public function sendVkGroup()
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $groupId = Yii::$app->settings->get('vk_group_id');
        if (empty($groupId)) {
            Yii::error("VK group_id is not configured", __METHOD__);
            return false;
        }

        // Используем русский язык по умолчанию
        $language = 'ru-RU';
        $message = $this->telegramConstructorMessage->getVkMessage($language);
        $imageLink = $this->telegramConstructorMessage->getImageLink($language);
        
        // Проверяем, является ли ссылка динамической (с плейсхолдером {user_id})
        $isDynamicLink = !empty($imageLink) && strpos($imageLink, '@') === 0 && strpos($imageLink, '{user_id}') !== false;

        // Получаем список участников группы с учетом фильтрации по аудитории
        $recipients = self::getAudience($this->audience_id, self::VK_GROUP, !empty($this->only_with_user));
        if (empty($recipients)) {
            Yii::error("VK: No recipients found for audience {$this->audience_id}, only_with_user: " . ($this->only_with_user ? 'true' : 'false'), __METHOD__);
            return false;
        }

        // Отправляем сообщения через очередь VK
        foreach ($recipients as $vkUserId) {
            $photo = null;
            if (!empty($imageLink)) {
                // Для динамических ссылок нужно подставить user_id из базы данных, а не vk_user_id
                // Ищем пользователя по vk_id
                $user = User::find()
                    ->where(['vk_id' => $vkUserId])
                    ->one();
                
                // Если пользователь найден, используем его user_id, иначе используем vk_user_id
                $userIdForUrl = $user ? $user->id : $vkUserId;
                
                if (!$user) {
                    Yii::warning("VK: User not found for vk_id {$vkUserId}, using vk_user_id for URL", __METHOD__);
                }
                
                $photo = $this->telegramConstructorMessage->getPubUrl('', $language, $userIdForUrl);
                
                if (empty($photo)) {
                    Yii::warning("VK: Empty photo URL for vk_user_id {$vkUserId}, user_id {$userIdForUrl}", __METHOD__);
                }
            }
            
            Yii::$app->queueVk->push(new SendVkMessageJob([
                'user_id' => $vkUserId,
                'message' => $message,
                'photo' => $photo,
            ]));
        }

        return true;
    }

    /**
     * Получение аудитории для рассылки
     * @param int $audienceId ID аудитории
     * @param int|null $botId ID бота/платформы (для фильтрации)
     * @param bool $onlyWithUser Отправлять только пользователям с привязанным user (только для VK)
     * @return array
     */
    public static function getAudience($audienceId, $botId = null, $onlyWithUser = false) {
        // Приводим к int для корректного сравнения
        $audienceId = (int)$audienceId;
        $botId = $botId !== null ? (int)$botId : null;
        
        // Фильтрация по платформе
        if ($botId === self::PERSONAL_BOT) {
            // Для Telegram параметр onlyWithUser игнорируется
            $query = User::find()
                ->select('DISTINCT(u.id)')
                ->alias('u')
                ->andWhere(['u.status' => User::STATUS_ACTIVE])
                ->andWhere('telegram_chat_id is NOT NULL')
                ->andWhere(['is_telegram_blocked' => 0]);
            
            if ($audienceId == self::AUDIENCE_TEST) {
                $query->andWhere(['IN', 'u.id', [509]]);
            } elseif ($audienceId == self::AUDIENCE_ALL) {
                // Без дополнительных фильтров
            } elseif ($audienceId == self::AUDIENCE_WINNER) {
                $query->andWhere(['IN', 'steam_id', [76561198161653962]]);
            } elseif ($audienceId == self::AUDIENCE_MODERATORS) {
                // Для Telegram: получаем ID пользователей с ролями ADMIN или MODERATOR
                $adminUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_ADMIN);
                $moderatorUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_MODERATOR);
                $moderatorUserIds = array_merge($adminUserIds, $moderatorUserIds);
                $moderatorUserIds = array_unique($moderatorUserIds);
                
                if (empty($moderatorUserIds)) {
                    return [];
                }
                
                $query->andWhere(['IN', 'u.id', $moderatorUserIds]);
            } else {
                return [];
            }

            return $query->createCommand()->queryColumn();
        } elseif ($botId === self::VK_GROUP) {
            // Для VK группы получаем список участников из базы данных (тех, кто разрешил отправку сообщений)
            $vkUsers = VkUser::getUsersWithPermission();
            
            if (empty($vkUsers)) {
                return [];
            }
            
            // Фильтруем по наличию привязанного user, если указано
            if ($onlyWithUser) {
                // Оставляем только тех, у кого есть привязанный user в базе данных
                $vkUsersWithUser = [];
                foreach ($vkUsers as $vkUserId) {
                    $user = User::find()
                        ->where(['vk_id' => $vkUserId])
                        ->exists();
                    if ($user) {
                        $vkUsersWithUser[] = $vkUserId;
                    }
                }
                $vkUsers = $vkUsersWithUser;
            }
            
            // Применяем фильтрацию по аудитории
            if ($audienceId == self::AUDIENCE_TEST) {
                // Для тестовой аудитории берем только первых 5 участников
                return [33610634];
            } elseif ($audienceId == self::AUDIENCE_ALL) {
                // Для всех пользователей возвращаем всех с разрешением
                return $vkUsers;
            } elseif ($audienceId == self::AUDIENCE_WINNER) {
                // Для победителей пока возвращаем всех (можно добавить фильтрацию позже)
                return $vkUsers;
            } elseif ($audienceId == self::AUDIENCE_MODERATORS) {
                // Для VK: получаем ID пользователей с ролями ADMIN или MODERATOR
                $adminUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_ADMIN);
                $moderatorUserIds = Yii::$app->authManager->getUserIdsByRole(Role::ROLE_MODERATOR);
                $moderatorUserIds = array_merge($adminUserIds, $moderatorUserIds);
                $moderatorUserIds = array_unique($moderatorUserIds);
                
                if (empty($moderatorUserIds)) {
                    return [];
                }
                
                // Получаем VK ID модераторов и админов (только тех, у кого есть привязанный VK аккаунт)
                $moderatorVkIds = User::find()
                    ->select('vk_id')
                    ->where(['IN', 'id', $moderatorUserIds])
                    ->andWhere(['IS NOT', 'vk_id', null])
                    ->column();
                
                if (empty($moderatorVkIds)) {
                    return [];
                }
                
                // Фильтруем VK пользователей, оставляя только тех, у кого есть привязанный user с ролью модератора/админа
                $filteredVkUsers = array_intersect($vkUsers, $moderatorVkIds);
                
                return array_values($filteredVkUsers);
            }
            
            return [];
        }

        return [];
    }

    /**
     * Gets query for [[TelegramConstructorMessage]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTelegramConstructorMessage()
    {
        return $this->hasOne(TelegramConstructorMessage::class, ['id' => 'telegram_constructor_message_id']);
    }
}
