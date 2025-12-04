<?php

namespace common\components\vk;

use common\models\user\User;
use common\models\servers\Servers;
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
    public function getNodeMessage($nodeId, $userId = null)
    {
        $username = $this->getUsername($userId);
        
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
        $data = json_decode($payload, true);
        if (empty($data)) {
            return null;
        }

        // Обработка команд через кнопки
        if (!empty($data['command'])) {
            switch ($data['command']) {
                case '/wipe':
                    return [
                        'message' => $this->getWipe(),
                        'keyboard' => null
                    ];
                case '/pop':
                    return [
                        'message' => $this->getOnline(),
                        'keyboard' => null
                    ];
                case '/ip':
                    return [
                        'message' => $this->getIp(),
                        'keyboard' => null
                    ];
            }
        }

        // Обработка узлов
        if (!empty($data['node'])) {
            return $this->getNodeMessage($data['node'], $userId);
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

        // Для всех остальных сообщений показываем приветствие
        return $this->getNodeMessage(self::NODE_GREETING, $userId);
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
            . "🎁 Промокод — Ваши промокоды" . PHP_EOL
            . "🤖 Бонус в телеграм — Информация о Telegram-боте" . PHP_EOL
            . "🛟 Написать в поддержку — Связаться с поддержкой" . PHP_EOL . PHP_EOL
            . "💡 Используйте кнопки ниже для быстрого доступа к функциям.";

        return $message;
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
            $date0 = new \DateTime($server->wipe);
            $date = new \DateTime($server->next_wipe);
            $date2 = new \DateTime($server->global_wipe);
            
            if ($k > 0) {
                $text .= PHP_EOL;
            }
            
            $name = $this->getServerName($server);
            $text .= PHP_EOL . "🖥️ {$name}";
            $text .= PHP_EOL . "   ⏮️ Последний: {$date0->format('d.m.Y в H:i')} МСК";
            $text .= PHP_EOL . "   ⏭️ Следующий: {$date->format('d.m.Y в H:i')} МСК";
            $text .= PHP_EOL . "   🌍 Глобал: {$date2->format('d.m.Y в H:i')} МСК";
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

