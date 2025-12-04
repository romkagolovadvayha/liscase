<?php

namespace common\components\vk;

use common\models\user\User;
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
            . "💡 Если хотите пожаловаться на игрока, нажмите F7 прямо в игре или напишите в поддержку.";

        $keyboard = [
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Промокод',
                        'payload' => json_encode(['action' => self::ACTION_PROMOCODE, 'node' => self::NODE_PROMOCODE])
                    ],
                    'color' => 'positive' // Зеленая кнопка
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Бонус в телеграм',
                        'payload' => json_encode(['action' => self::ACTION_TELEGRAM, 'node' => self::NODE_TELEGRAM])
                    ],
                    'color' => 'primary' // Синяя кнопка
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Написать в поддержку',
                        'payload' => json_encode(['action' => self::ACTION_SUPPORT, 'node' => self::NODE_SUPPORT])
                    ],
                    'color' => 'negative' // Красная кнопка
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
            . "🆕 <b>START</b> — для новых игроков" . PHP_EOL
            . "🔄 <b>WIPE</b> — к свежему вайпу" . PHP_EOL . PHP_EOL
            . "✨ Активируйте их на сайте " . $siteDomain . " и получите бонус перед стартом!";

        $keyboard = [
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Бонус в телеграм',
                        'payload' => json_encode(['action' => self::ACTION_TELEGRAM, 'node' => self::NODE_TELEGRAM])
                    ],
                    'color' => 'primary' // Синяя кнопка
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Написать в поддержку',
                        'payload' => json_encode(['action' => self::ACTION_SUPPORT, 'node' => self::NODE_SUPPORT])
                    ],
                    'color' => 'negative' // Красная кнопка
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
                        'label' => 'Промокод',
                        'payload' => json_encode(['action' => self::ACTION_PROMOCODE, 'node' => self::NODE_PROMOCODE])
                    ],
                    'color' => 'positive' // Зеленая кнопка
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Написать в поддержку',
                        'payload' => json_encode(['action' => self::ACTION_SUPPORT, 'node' => self::NODE_SUPPORT])
                    ],
                    'color' => 'negative' // Красная кнопка
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
            . "   • Нажмите <b>F7</b> прямо в игре" . PHP_EOL
            . "   • Или напишите в поддержку на сайте" . PHP_EOL . PHP_EOL
            . "🔗 " . $siteDomain . "/support";

        $keyboard = [
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Промокод',
                        'payload' => json_encode(['action' => self::ACTION_PROMOCODE, 'node' => self::NODE_PROMOCODE])
                    ],
                    'color' => 'positive' // Зеленая кнопка
                ]
            ],
            [
                [
                    'action' => [
                        'type' => 'text',
                        'label' => 'Бонус в телеграм',
                        'payload' => json_encode(['action' => self::ACTION_TELEGRAM, 'node' => self::NODE_TELEGRAM])
                    ],
                    'color' => 'primary' // Синяя кнопка
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
        if (empty($data) || empty($data['node'])) {
            return null;
        }

        return $this->getNodeMessage($data['node'], $userId);
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
        
        // Если сообщение начинается с команды /start или пустое - показываем приветствие
        if (empty($text) || strpos($text, '/start') === 0 || strpos($text, 'start') === 0) {
            return $this->getNodeMessage(self::NODE_GREETING, $userId);
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
}

