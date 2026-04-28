<?php

namespace common\components\vk;

use common\models\box\Box;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * Система обработки сообщений VK бота
 * Реализует навигацию по узлам согласно схеме
 */
class VkBotSystem extends BaseObject
{
    /**
     * Узлы бота
     */
    const NODE_GREETING = 'PB-1';      // приветствие PB - 1
    const NODE_PROMOCODE = 'PB-3';     // ваш промокод PB - 3
    const NODE_TELEGRAM = 'PB-24';     // бот телеграм PB - 24
    const NODE_SUPPORT = 'PB-25';      // поддержка PB - 25

    /**
     * Действия кнопок
     */
    const ACTION_PROMOCODE = 'promocode';
    const ACTION_TELEGRAM = 'telegram';
    const ACTION_SUPPORT = 'support';

    /**
     * Получение сообщения для узла
     * @param string $nodeId ID узла
     * @param int|null $userId ID пользователя VK
     * @return array ['message' => string, 'keyboard' => array|null]
     */
    public function getNodeMessage($nodeId, $userId = null, $skipUsername = false)
    {
        $username = '';
        if (!$skipUsername) {
            $username = $this->getUsername($userId);
        }
        
        switch ($nodeId) {
            case self::NODE_GREETING:
                return $this->getGreetingMessage($username);
            
            case self::NODE_PROMOCODE:
                return $this->getPromocodeMessage();
            
            case self::NODE_TELEGRAM:
                return $this->getTelegramMessage();
            
            case self::NODE_SUPPORT:
                return $this->getSupportMessage();
            
            default:
                // По умолчанию возвращаем приветствие
                return $this->getGreetingMessage($username);
        }
    }

    /**
     * Приветствие PB - 1
     * @param string $username Имя пользователя
     * @return array
     */
    private function getGreetingMessage($username = '')
    {
        $greeting = !empty($username) ? "Здравствуйте, {$username}!" : "Здравствуйте!";
        
        $message = "👋 {$greeting}" . PHP_EOL . PHP_EOL
            . "Добро пожаловать в наш сервис! Выберите нужное действие ниже:" . PHP_EOL . PHP_EOL
            . "📋 Что умеет бот:" . PHP_EOL
            . "   • /pop — Онлайн на серверах" . PHP_EOL
            . "   • /wipe — Календарь вайпов" . PHP_EOL
            . "   • /ip — IP-адреса серверов" . PHP_EOL
            . "   • /balance — Баланс аккаунта (нужна привязка ВК на сайте)" . PHP_EOL
            . "   • /bonus — Ежедневный бонус (бесплатная рулетка)" . PHP_EOL
            . "   • /raid_alert — Вкл/выкл оповещения о рейдах" . PHP_EOL
            . "   • /ban_alert — Вкл/выкл оповещения о банах" . PHP_EOL
            . "   • /help — Список всех команд" . PHP_EOL . PHP_EOL
            . "💡 Если хотите пожаловаться на игрока, нажмите F7 прямо в игре или напишите в поддержку.";

        $keyboard = [
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '📅 Вайпы',
                        'payload' => json_encode(['action' => 'wipe', 'command' => '/wipe'])
                    ],
                    'color' => 'primary'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '👥 Онлайн',
                        'payload' => json_encode(['action' => 'pop', 'command' => '/pop'])
                    ],
                    'color' => 'primary'
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🔗 IP серверов',
                        'payload' => json_encode(['action' => 'ip', 'command' => '/ip'])
                    ],
                    'color' => 'primary'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🎁 Промокод',
                        'payload' => json_encode(['action' => self::ACTION_PROMOCODE, 'node' => self::NODE_PROMOCODE])
                    ],
                    'color' => 'positive'
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '💰 Баланс',
                        'payload' => json_encode(['command' => '/balance'])
                    ],
                    'color' => 'secondary'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🎁 Бонус',
                        'payload' => json_encode(['command' => '/bonus'])
                    ],
                    'color' => 'positive'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🚨 Рейды',
                        'payload' => json_encode(['command' => '/raid_alert'])
                    ],
                    'color' => 'secondary'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🔔 Баны',
                        'payload' => json_encode(['command' => '/ban_alert'])
                    ],
                    'color' => 'secondary'
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🤖 Telegram бот',
                        'payload' => json_encode(['action' => self::ACTION_TELEGRAM, 'node' => self::NODE_TELEGRAM])
                    ],
                    'color' => 'primary'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🛟 Поддержка',
                        'payload' => json_encode(['action' => self::ACTION_SUPPORT, 'node' => self::NODE_SUPPORT])
                    ],
                    'color' => 'negative'
                ]
            ]
        ];

        return [
            'message' => $message,
            'keyboard' => [
                'one_time' => false,
                'buttons' => $keyboard
            ]
        ];
    }

    /**
     * Ваш промокод PB - 3
     * @return array
     */
    private function getPromocodeMessage()
    {
        $siteDomain = Yii::$app->settings->get('site_domain');
        
        $message = "🎁 Ваши промокоды:" . PHP_EOL . PHP_EOL
            . "🆕 START — для новых игроков" . PHP_EOL
            . "🔄 WIPE — к свежему вайпу" . PHP_EOL . PHP_EOL
            . "✨ Активируйте их на сайте " . $siteDomain . " и получите бонус перед стартом!";

        $keyboard = [
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🤖 Telegram бот',
                        'payload' => json_encode(['action' => self::ACTION_TELEGRAM, 'node' => self::NODE_TELEGRAM])
                    ],
                    'color' => 'primary'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🛟 Поддержка',
                        'payload' => json_encode(['action' => self::ACTION_SUPPORT, 'node' => self::NODE_SUPPORT])
                    ],
                    'color' => 'negative'
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🏠 Главное меню',
                        'payload' => json_encode(['action' => 'greeting', 'node' => self::NODE_GREETING])
                    ],
                    'color' => 'secondary'
                ]
            ]
        ];

        return [
            'message' => $message,
            'keyboard' => [
                'one_time' => false,
                'buttons' => $keyboard
            ]
        ];
    }

    /**
     * Бот телеграм PB - 24
     * @return array
     */
    private function getTelegramMessage()
    {
        $telegramLink = Yii::$app->settings->get('social_telegram');
        
        $message = "🤖 Хочешь получать ежедневные бонусы?" . PHP_EOL . PHP_EOL
            . "📱 Авторизуйся в нашем Telegram-боте и забирай награды каждый день!" . PHP_EOL . PHP_EOL
            . "🔗 " . $telegramLink;

        $keyboard = [
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🎁 Промокод',
                        'payload' => json_encode(['action' => self::ACTION_PROMOCODE, 'node' => self::NODE_PROMOCODE])
                    ],
                    'color' => 'positive'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🛟 Поддержка',
                        'payload' => json_encode(['action' => self::ACTION_SUPPORT, 'node' => self::NODE_SUPPORT])
                    ],
                    'color' => 'negative'
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🏠 Главное меню',
                        'payload' => json_encode(['action' => 'greeting', 'node' => self::NODE_GREETING])
                    ],
                    'color' => 'secondary'
                ]
            ]
        ];

        return [
            'message' => $message,
            'keyboard' => [
                'one_time' => false,
                'buttons' => $keyboard
            ]
        ];
    }

    /**
     * Поддержка PB - 25
     * @return array
     */
    private function getSupportMessage()
    {
        $siteDomain = Yii::$app->settings->get('site_domain');
        
        $message = "🛟 Поддержка" . PHP_EOL . PHP_EOL
            . "📝 Чтобы пожаловаться на игрока:" . PHP_EOL
            . "   • Нажмите F7 прямо в игре" . PHP_EOL
            . "   • Или напишите в поддержку на сайте" . PHP_EOL . PHP_EOL
            . "🔗 " . $siteDomain . "/support";

        $keyboard = [
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🎁 Промокод',
                        'payload' => json_encode(['action' => self::ACTION_PROMOCODE, 'node' => self::NODE_PROMOCODE])
                    ],
                    'color' => 'positive'
                ],
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🤖 Telegram бот',
                        'payload' => json_encode(['action' => self::ACTION_TELEGRAM, 'node' => self::NODE_TELEGRAM])
                    ],
                    'color' => 'primary'
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => '🏠 Главное меню',
                        'payload' => json_encode(['action' => 'greeting', 'node' => self::NODE_GREETING])
                    ],
                    'color' => 'secondary'
                ]
            ]
        ];

        return [
            'message' => $message,
            'keyboard' => [
                'one_time' => false,
                'buttons' => $keyboard
            ]
        ];
    }

    /**
     * Обработка нажатия на кнопку
     * @param string $payload JSON payload кнопки
     * @param int|null $userId ID пользователя VK
     * @return array|null
     */
    public function handleButtonClick($payload, $userId = null)
    {
        // Если payload уже является массивом, используем его напрямую
        if (is_array($payload)) {
            $data = $payload;
        } else {
            $data = json_decode($payload, true);
        }
        
        if (empty($data) || !is_array($data)) {
            return null;
        }

        // Обработка команд через кнопки
        if (!empty($data['command'])) {
            switch ($data['command']) {
                case '/wipe':
                    return [
                        'message' => $this->getWipe(),
                        'keyboard' => $this->getGreetingMessage(null)['keyboard']
                    ];
                case '/pop':
                    return [
                        'message' => $this->getOnline(),
                        'keyboard' => $this->getGreetingMessage(null)['keyboard']
                    ];
                case '/ip':
                    return [
                        'message' => $this->getIp(),
                        'keyboard' => $this->getGreetingMessage(null)['keyboard']
                    ];
                case '/balance':
                    return [
                        'message' => $this->buildBalanceMessage($userId),
                        'keyboard' => $this->getGreetingMessage(null)['keyboard']
                    ];
                case '/bonus':
                    return [
                        'message' => $this->buildBonusMessage($userId),
                        'keyboard' => $this->getGreetingMessage(null)['keyboard']
                    ];
                case '/raid_alert':
                    return [
                        'message' => $this->buildRaidAlertMessage($userId),
                        'keyboard' => $this->getGreetingMessage(null)['keyboard']
                    ];
                case '/ban_alert':
                    return [
                        'message' => $this->buildBanAlertMessage($userId),
                        'keyboard' => $this->getGreetingMessage(null)['keyboard']
                    ];
            }
        }

        // Обработка узлов через action или node
        $nodeId = null;
        if (!empty($data['node'])) {
            $nodeId = $data['node'];
        } elseif (!empty($data['action'])) {
            // Если есть action, определяем node по action
            switch ($data['action']) {
                case self::ACTION_PROMOCODE:
                    $nodeId = self::NODE_PROMOCODE;
                    break;
                case self::ACTION_TELEGRAM:
                    $nodeId = self::NODE_TELEGRAM;
                    break;
                case self::ACTION_SUPPORT:
                    $nodeId = self::NODE_SUPPORT;
                    break;
                case 'greeting':
                    $nodeId = self::NODE_GREETING;
                    break;
            }
        }
        
        if ($nodeId) {
            // Пропускаем получение username для ускорения ответа
            return $this->getNodeMessage($nodeId, $userId, true);
        }

        return null;
    }

    /**
     * Обработка текстового сообщения
     * @param string $text Текст сообщения
     * @param int|null $userId ID пользователя VK
     * @return array
     */
    public function handleTextMessage($text, $userId = null)
    {
        $text = trim($text);
        $textLower = mb_strtolower($text, 'UTF-8');
        
        // Если сообщение начинается с команды /start или пустое - показываем приветствие
        if (empty($text) || strpos($text, '/start') === 0 || strpos($text, 'start') === 0) {
            return $this->getNodeMessage(self::NODE_GREETING, $userId);
        }

        // Обработка текста кнопок (когда пользователь нажимает кнопку, VK отправляет текст кнопки)
        // Убираем эмодзи для сравнения
        $textWithoutEmoji = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $text);
        $textWithoutEmoji = trim($textWithoutEmoji);
        $textWithoutEmojiLower = mb_strtolower($textWithoutEmoji, 'UTF-8');
        
        // Проверяем текст кнопок
        if (mb_stripos($textWithoutEmojiLower, 'главное меню') !== false || mb_stripos($text, '🏠') !== false) {
            return $this->getNodeMessage(self::NODE_GREETING, $userId);
        }
        
        if (mb_stripos($textWithoutEmojiLower, 'промокод') !== false || mb_stripos($text, '🎁') !== false) {
            return $this->getNodeMessage(self::NODE_PROMOCODE, $userId);
        }
        
        // Проверка для Telegram бота (текст кнопки: "🤖 Telegram бот")
        if (mb_stripos($textWithoutEmojiLower, 'telegram') !== false || 
            mb_stripos($textWithoutEmojiLower, 'телеграм') !== false || 
            mb_stripos($text, '🤖') !== false ||
            (mb_stripos($textWithoutEmojiLower, 'бот') !== false && mb_stripos($textWithoutEmojiLower, 'telegram') !== false) ||
            (mb_stripos($textWithoutEmojiLower, 'бот') !== false && mb_stripos($textWithoutEmojiLower, 'телеграм') !== false) ||
            $textWithoutEmojiLower === 'telegram бот' ||
            $textWithoutEmojiLower === 'telegram bot') {
            return $this->getNodeMessage(self::NODE_TELEGRAM, $userId, true);
        }
        
        if (mb_stripos($textWithoutEmojiLower, 'поддержка') !== false || mb_stripos($text, '🛟') !== false) {
            return $this->getNodeMessage(self::NODE_SUPPORT, $userId);
        }
        
        if (mb_stripos($textWithoutEmojiLower, 'вайп') !== false || mb_stripos($text, '📅') !== false) {
            return [
                'message' => $this->getWipe(),
                'keyboard' => null
            ];
        }
        
        if (mb_stripos($textWithoutEmojiLower, 'онлайн') !== false || mb_stripos($text, '👥') !== false) {
            return [
                'message' => $this->getOnline(),
                'keyboard' => null
            ];
        }
        
        if (mb_stripos($textWithoutEmojiLower, 'ip') !== false || mb_stripos($text, '🔗') !== false) {
            return [
                'message' => $this->getIp(),
                'keyboard' => null
            ];
        }

        if (mb_stripos($textWithoutEmojiLower, 'баланс') !== false || mb_stripos($text, '💰') !== false) {
            return [
                'message' => $this->buildBalanceMessage($userId),
                'keyboard' => null
            ];
        }

        if (mb_stripos($textWithoutEmojiLower, 'бонус') !== false
            && mb_stripos($textWithoutEmojiLower, 'телеграм') === false
            && mb_stripos($textWithoutEmojiLower, 'telegram') === false) {
            return [
                'message' => $this->buildBonusMessage($userId),
                'keyboard' => null
            ];
        }

        if (mb_stripos($textWithoutEmojiLower, 'рейд') !== false && mb_stripos($textWithoutEmojiLower, 'оповещ') !== false) {
            return [
                'message' => $this->buildRaidAlertMessage($userId),
                'keyboard' => null
            ];
        }

        if ((mb_stripos($textWithoutEmojiLower, 'бан') !== false && mb_stripos($textWithoutEmojiLower, 'оповещ') !== false)
            || mb_stripos($textWithoutEmojiLower, 'ban_alert') !== false) {
            return [
                'message' => $this->buildBanAlertMessage($userId),
                'keyboard' => null
            ];
        }

        // Обработка команд
        switch ($text) {
            case '/help':
            case 'help':
                return [
                    'message' => $this->getHelp(),
                    'keyboard' => null
                ];
            
            case '/wipe':
            case 'wipe':
                return [
                    'message' => $this->getWipe(),
                    'keyboard' => null
                ];
            
            case '/pop':
            case 'pop':
                return [
                    'message' => $this->getOnline(),
                    'keyboard' => null
                ];
            
            case '/ip':
            case 'ip':
                return [
                    'message' => $this->getIp(),
                    'keyboard' => null
                ];

            case '/balance':
            case 'balance':
                return [
                    'message' => $this->buildBalanceMessage($userId),
                    'keyboard' => null
                ];

            case '/bonus':
            case 'bonus':
                return [
                    'message' => $this->buildBonusMessage($userId),
                    'keyboard' => null
                ];

            case '/raid_alert':
            case 'raid_alert':
                return [
                    'message' => $this->buildRaidAlertMessage($userId),
                    'keyboard' => null
                ];

            case '/ban_alert':
            case 'ban_alert':
                return [
                    'message' => $this->buildBanAlertMessage($userId),
                    'keyboard' => null
                ];
        }

        // Проверяем, есть ли в сообщении слово "вайп"
        if (mb_stripos($textLower, 'вайп') !== false) {
            return [
                'message' => $this->getWipe(),
                'keyboard' => null
            ];
        }

        // Проверяем, является ли текст кодом подтверждения
        $user = \common\models\user\UserConfirmCode::getUserByVkCode($text);
        if ($user) {
            // Код найден - обрабатывается в VkController
            // Здесь просто возвращаем приветствие
            return $this->getNodeMessage(self::NODE_GREETING, $userId);
        }

        // Для всех остальных нераспознанных сообщений не отвечаем
        // Приветствие отправляется только при /start или пустом сообщении
        return null;
    }

    /**
     * Получение имени пользователя
     * @param int|null $userId ID пользователя VK
     * @return string
     */
    private function getUsername($userId = null)
    {
        if (empty($userId)) {
            return '';
        }

        try {
            // Пытаемся найти пользователя в базе по vk_id
            $user = User::find()
                ->andWhere(['vk_id' => $userId])
                ->one();

            if ($user && !empty($user->username)) {
                return $user->username;
            }

            // Если не нашли в базе, пытаемся получить из VK API
            $vkApi = new VkApiHelper();
            $vkApi->setAccessToken(Yii::$app->settings->get('vk_token'));
            
            $usersInfo = $vkApi->getUsersInfo([$userId]);
            if (!empty($usersInfo[0]['first_name'])) {
                $firstName = $usersInfo[0]['first_name'];
                $lastName = $usersInfo[0]['last_name'] ?? '';
                return trim($firstName . ' ' . $lastName);
            }
        } catch (\Exception $e) {
            Yii::error("VkBotSystem: Error getting username for user {$userId}: " . $e->getMessage(), __METHOD__);
        }

        return '';
    }

    /**
     * Получение списка доступных команд
     * @return string
     */
    private function getHelp()
    {
        $message = "📋 Доступные команды:" . PHP_EOL . PHP_EOL
            . "👥 /pop — Онлайн на серверах" . PHP_EOL
            . "📅 /wipe — Календарь вайпов" . PHP_EOL
            . "🔗 /ip — IP-адреса серверов" . PHP_EOL
            . "💰 /balance — Баланс аккаунта (после привязки ВК на сайте)" . PHP_EOL
            . "🎁 /bonus — Ежедневный бонус (как в Telegram-боте)" . PHP_EOL
            . "🚨 /raid_alert — Оповещения о рейдах (вкл/выкл, как в Telegram)" . PHP_EOL
            . "🔔 /ban_alert — Оповещения о банах (вкл/выкл)" . PHP_EOL
            . "🎁 Промокод — Ваши промокоды" . PHP_EOL
            . "🤖 Бонус в телеграм — Информация о Telegram-боте" . PHP_EOL
            . "🛟 Написать в поддержку — Связаться с поддержкой" . PHP_EOL . PHP_EOL
            . "💡 Используйте кнопки ниже для быстрого доступа к функциям.";

        return $message;
    }

    /**
     * @param int|null $vkUserId from_id из Callback API
     */
    private function findUserByVkId($vkUserId): ?User
    {
        if (empty($vkUserId)) {
            return null;
        }

        return User::find()->andWhere(['vk_id' => (int)$vkUserId])->one();
    }

    /**
     * @param int|null $vkUserId
     */
    private function buildBalanceMessage($vkUserId): string
    {
        $cacheKey = 'VkBotSystem_getBalance_' . (int)$vkUserId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $user = $this->findUserByVkId($vkUserId);
        if (empty($user)) {
            $domain = Yii::$app->settings->get('site_domain');

            return '🔒 Требуется привязка аккаунта' . PHP_EOL . PHP_EOL
                . 'Чтобы смотреть баланс, привяжите ВКонтакте в личном кабинете на сайте и подтвердите код в этом чате.' . PHP_EOL
                . 'Сайт: https://' . $domain;
        }
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 Доступ запрещён' . PHP_EOL . PHP_EOL . 'Ваш аккаунт заблокирован.';
            Yii::$app->cache->set($cacheKey, $return, 60);

            return $return;
        }

        $personalBalance = $user->getPersonalBalance();
        $skinsBalance = $user->getSkinsBalance();
        $domain = Yii::$app->settings->get('site_domain');
        $text = '💰 Баланс аккаунта' . PHP_EOL
            . 'Пользователь: ' . $user->username . PHP_EOL . PHP_EOL
            . 'Лицевой счёт: ' . $personalBalance->getBalanceFormat() . ' РУБ' . PHP_EOL . PHP_EOL
            . 'Скины: ' . $skinsBalance->getBalanceFormat() . ' РУБ' . PHP_EOL . PHP_EOL
            . 'Магазин: https://' . $domain;

        Yii::$app->cache->set($cacheKey, $text, 30);

        return $text;
    }

    /**
     * Ежедневный бонус (бесплатная рулетка) — та же логика, что {@see PersonalBotSystem::getBonus}.
     *
     * @param int|null $vkUserId
     */
    private function buildBonusMessage($vkUserId): string
    {
        $cacheKey = 'VkBotSystem_getBonus_' . (int)$vkUserId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $user = $this->findUserByVkId($vkUserId);
        if (empty($user)) {
            $domain = Yii::$app->settings->get('site_domain');

            return '🔒 Требуется привязка аккаунта' . PHP_EOL . PHP_EOL
                . 'Чтобы получать ежедневный бонус, привяжите ВКонтакте на сайте и подтвердите код в этом чате.' . PHP_EOL
                . 'Сайт: https://' . $domain;
        }
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 Доступ запрещён' . PHP_EOL . PHP_EOL . 'Ваш аккаунт заблокирован.';
            Yii::$app->cache->set($cacheKey, $return, 60);

            return $return;
        }

        $box = Box::findOne(14);
        if ($box === null) {
            return '⚠️ Бонус временно недоступен. Попробуйте позже.';
        }

        $nextOpenFreeBoxDate = Box::getNextOpenFreeBoxDate($user->id);
        if (!empty($nextOpenFreeBoxDate)) {
            $date = new \DateTime($nextOpenFreeBoxDate);
            $return = '⏰ Бонус уже получен' . PHP_EOL . PHP_EOL
                . 'Вы уже получили награду сегодня.' . PHP_EOL
                . 'Следующий бонус: ' . $date->format('d.m.Y в H:i') . ' МСК';
            Yii::$app->cache->set($cacheKey, $return, 60);

            return $return;
        }

        $userBoxId = UserBox::createRecord($user->id, $box->id);
        $userBox = UserBox::findOne($userBoxId);
        if ($userBox === null) {
            return '⚠️ Не удалось выдать бонус. Попробуйте позже.';
        }

        [$boxDropCarousel, $number] = $userBox->box->_getDropFinal();
        $userBox->status = UserBox::STATUS_OPENED;
        $userBox->save(false);

        $dropName = Yii::t('database', $boxDropCarousel[$number]['boxDrop']->drop->name);
        $dropCount = $boxDropCarousel[$number]['count'];

        if ($boxDropCarousel[$number]['boxDrop']->drop->id != 843) {
            UserDrop::createRecord(
                $user->id,
                $boxDropCarousel[$number]['boxDrop']->drop->id,
                $box->id,
                null,
                UserDrop::STATUS_ACTIVE,
                false,
                $boxDropCarousel[$number]['count']
            );
        } else {
            $userBalance = $user->getPersonalBalance();
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $boxDropCarousel[$number]['count'];
            $profit->user_balance_id = $userBalance->id;
            $profit->comment = Yii::t('common', 'Выигрыш в бесплатной рулетке', [], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }

        return '🎉 Поздравляем!' . PHP_EOL . PHP_EOL
            . 'Вы получили награду:' . PHP_EOL
            . '🎁 ' . $dropName . ' × ' . $dropCount;
    }

    /**
     * @param int|null $vkUserId
     */
    private function buildRaidAlertMessage($vkUserId): string
    {
        $cacheKey = 'VkBotSystem_getRaidAlert_' . (int)$vkUserId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $user = $this->findUserByVkId($vkUserId);
        if (empty($user)) {
            return '🔒 Сначала привяжите ВКонтакте к аккаунту на сайте и подтвердите код в этом чате.';
        }
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 Доступ запрещён' . PHP_EOL . PHP_EOL . 'Ваш аккаунт заблокирован.';
            Yii::$app->cache->set($cacheKey, $return, 60);

            return $return;
        }

        Yii::$app->cache->set($cacheKey, '⏳ Слишком часто, попробуйте позже.', 10);
        if ($user->raid_notify) {
            $user->raid_notify = 0;
            $user->save(false);

            return '🔕 Уведомления отключены' . PHP_EOL . PHP_EOL . 'Оповещения о рейдах выключены (как в Telegram).';
        }
        $user->raid_notify = 1;
        $user->save(false);

        return '🔔 Уведомления включены' . PHP_EOL . PHP_EOL
            . 'Будем присылать оповещения о рейдах в этот чат, пока включено в боте и на сайте.';
    }

    /**
     * @param int|null $vkUserId
     */
    private function buildBanAlertMessage($vkUserId): string
    {
        $cacheKey = 'VkBotSystem_getBanAlert_' . (int)$vkUserId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $user = $this->findUserByVkId($vkUserId);
        if (empty($user)) {
            return '🔒 Сначала привяжите ВКонтакте к аккаунту на сайте и подтвердите код в этом чате.';
        }
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 Доступ запрещён' . PHP_EOL . PHP_EOL . 'Ваш аккаунт заблокирован.';
            Yii::$app->cache->set($cacheKey, $return, 60);

            return $return;
        }

        Yii::$app->cache->set($cacheKey, '⏳ Слишком часто, попробуйте позже.', 10);
        if ($user->ban_notify) {
            $user->ban_notify = 0;
            $user->save(false);

            return '🔕 Уведомления отключены' . PHP_EOL . PHP_EOL . 'Оповещения о банах выключены.';
        }
        $user->ban_notify = 1;
        $user->save(false);

        return '🔔 Уведомления включены' . PHP_EOL . PHP_EOL
            . 'Будем присылать оповещения о банах по вашим жалобам в этот чат.';
    }

    /**
     * Получение календаря вайпов
     * @return string
     */
    private function getWipe()
    {
        $cacheKey = 'VkBotSystem_getWipe';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $text = "📅 Календарь вайпов" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        foreach ($servers as $k => $server) {
            $lastAt = $server->getFactWipe() ?: $server->wipe;
            $nextAt = $server->getFactNextWipe() ?: $server->next_wipe;
            $globalAt = $server->getFactGlobalWipe() ?: $server->global_wipe;

            if ($k > 0) {
                $text .= PHP_EOL;
            }

            $name = $this->getServerName($server);
            $text .= PHP_EOL . "🖥️ {$name}";
            $text .= PHP_EOL . '   ⏮️ Последний: ' . $this->formatVkWipeDateTime($lastAt);
            $text .= PHP_EOL . '   ⏭️ Следующий: ' . $this->formatVkWipeDateTime($nextAt);
            $text .= PHP_EOL . '   🌍 Глобал: ' . $this->formatVkWipeDateTime($globalAt);
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    /**
     * Получение информации об онлайне на серверах
     * @return string
     */
    private function getOnline()
    {
        $cacheKey = 'VkBotSystem_getOnline';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $text = "👥 Онлайн на серверах" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        foreach ($servers as $k => $server) {
            $lineSize = 10;
            $pl = $server->players + $server->joined;
            $lineGreen = ceil($lineSize / $server->max * (ceil($pl / 10) * 10));
            if ($lineGreen > $lineSize) {
                $lineGreen = $lineSize;
            }
            $lineSize -= $lineGreen;
            
            if ($k > 0) {
                $text .= PHP_EOL;
            }
            
            $name = $this->getServerName($server);
            $text .= PHP_EOL . "🖥️ {$name}";
            $text .= PHP_EOL;
            
            for ($i = 0; $i < $lineGreen; $i++) {
                $text .= "🟩";
            }
            for ($i = 0; $i < $lineSize; $i++) {
                $text .= "⬜";
            }
            
            $percentage = round(($pl / $server->max) * 100);
            $text .= PHP_EOL . "   👤 {$pl}/{$server->max} ({$percentage}%)";
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    /**
     * Получение IP-адресов серверов
     * @return string
     */
    private function getIp()
    {
        $cacheKey = 'VkBotSystem_getIp';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $text = "🔗 IP-адреса серверов" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        foreach ($servers as $k => $server) {
            if ($k > 0) {
                $text .= PHP_EOL;
            }
            
            $name = $this->getServerName($server);
            $text .= PHP_EOL . "🖥️ {$name}";
            $text .= PHP_EOL . "   📍 connect {$server->ip}:{$server->port}";
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    /**
     * Дата/время вайпа для текста VK (календарь или поле сервера).
     *
     * @param string|null $raw Y-m-d H:i:s или совместимое значение
     */
    private function formatVkWipeDateTime($raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }
        try {
            $date = new \DateTime((string) $raw);

            return $date->format('d.m.Y в H:i') . ' МСК';
        } catch (\Throwable $e) {
            return '—';
        }
    }

    /**
     * Получение названия сервера с типом вайпа
     * @param Servers $server
     * @return string
     */
    private function getServerName($server)
    {
        $wipeTypeLabel = '';
        if ($server->wipe_type === 7) {
            $wipeTypeLabel = 'Недельный';
        } elseif ($server->wipe_type === 14) {
            $wipeTypeLabel = 'Двухнедельный';
        } elseif ($server->wipe_type === 30) {
            $wipeTypeLabel = 'Месячный';
        }
        return '[' . Yii::t('database', $server->monitoring_name) . '] | ' . $wipeTypeLabel;
    }
}

