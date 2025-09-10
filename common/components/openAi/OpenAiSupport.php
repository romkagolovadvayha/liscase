<?php

namespace common\components\openAi;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\user\User;
use GuzzleHttp\Client;
use Yii;

class OpenAiSupport extends \yii\base\Component
{
    public $temperature = 0.2;

    /**
     * @var Client
     */
    private $client;

    public function init()
    {
        parent::init();
        $clientConfig = [
            'base_uri' => 'https://api.openai.com/v1/',
            'headers'  => [
                'Authorization' => 'Bearer ' . Yii::$app->settings->get('openAi_apiKey'),
                'Content-Type'  => 'application/json',
            ],
        ];

        // Добавим прокси, если задано
        if (!empty(Yii::$app->settings->get('proxy_ip'))) {
            $proxy = "http://" . Yii::$app->settings->get('proxy_username') . ":" . Yii::$app->settings->get('proxy_password') . "@" . Yii::$app->settings->get('proxy_ip');
            $clientConfig['proxy'] = [
                'http'  => $proxy,
                'https' => $proxy,
            ];
        }

        $this->client = new Client($clientConfig);
    }

    /**
     * Основной метод: отвечает на сообщение, используя базу знаний
     *
     * @param string $userMessage
     * @param array  $chatHistory
     * @param        $username
     * @param        $server
     * @param null   $ticketId
     * @param User   $user
     *
     * @return string|null
     */
    public function getReply(
        string $userMessage,
        string $username,
        string $server,
        array $chatHistory = [],
        ?int $ticketId = null,
        $user = null
    ): ?string {
        try {
            $knowledge = $this->loadKnowledgeBase();

            $p = new PersonalBotSystem();

            // Контекст (не инструкции!)
            $context = [];
            $context[] = "Ник игрока: " . htmlspecialchars($username);
            $context[] = "Сервер: " . trim((string)$server);

            $context[] = "Как подключиться к серверу?";
            $context[] = "Подключение через консоль F1. Список IP серверов:";
            $context[] = trim($p->getIp());

            $context[] = "Когда вайп?";
            $context[] = "Даты вайпов на серверах:";
            $context[] = trim($p->getWipe());

            if (!empty($user)) {
                /** @var Reports[] $reports */
                $reports = Reports::find()
                                  ->andWhere(['steam_id' => $user->steam_id])
                                  ->orderBy(['id' => SORT_DESC])
                                  ->limit(3)
                                  ->all();

                if (empty($reports)) {
                    $context[] = "Игрок не отправлял ни одной жалобы. Если он жалуется на игрока, предложи отправить жалобу через {PARAM_COMMAND_F7} в игре.";
                } else {
                    $context[] = "Если игрок жалуется и не указал ник, предположи по последним жалобам и уточни у него. Последние жалобы:";
                    foreach ($reports as $item) {
                        $usernameitem = htmlspecialchars($item->user->username ?? 'неизвестно');
                        $reason = htmlspecialchars($item->reason ?? 'не указана');
                        $date = htmlspecialchars($item->created_at ?? '');
                        $steamId = htmlspecialchars($item->user->steam_id ?? '');
                        $context[] = "- {$usernameitem} (steam_id: {$steamId}; причина: {$reason}; дата: {$date})";
                    }
                }

                if (!empty($user->userProfile->trade_link)) {
                    $context[] = "Trade-ссылка Steam игрока: {$user->userProfile->trade_link}";
                }
            }

            if (!empty($ticketId)) {
                $context[] = "Ссылка для закрытия тикета: https://prostoj.store/support/ticket-close?id={$ticketId}";
            }

            // Если нужно логировать контекст — делай это осознанно
            // Yii::$app->telegramChats->sendMessage(implode("\n", $context));

            $messages = [];

            // Инструкции — только в system
            $systemInstructions = Yii::$app->settings->get('openAi_instructions')
                . "\n\nВажно: отвечай простым, человеческим комментарием на текст статьи. Без формата JSON, без метаданных, без обращений к разработчикам. Коротко и по делу.";

            $messages[] = ['role' => 'system', 'content' => $systemInstructions];

            // Подкладываем базу знаний и динамический контекст единым блоком как user
            $messages[] = [
                'role' => 'user',
                'content' =>
                    "Контекст (справка для ответа, не обязательно упоминать явно):\n"
                    . trim($knowledge) . "\n\n"
                    . implode("\n", $context)
            ];

            // История: строго по ролям
            foreach ($chatHistory as $item) {
                if (!empty($item['user'])) {
                    $messages[] = ['role' => 'user', 'content' => (string)$item['user']];
                }
                if (!empty($item['bot'])) {
                    $messages[] = ['role' => 'assistant', 'content' => (string)$item['bot']];
                }
            }

            // ТЕКУЩЕЕ сообщение пользователя — обязательно последним
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => Yii::$app->settings->get('openAi_model'),
                    'messages' => $messages,
                    'temperature' => $this->temperature ?? 0.7,
                    'max_tokens' => 350, // чтобы не расплывался
                ],
                'timeout' => 20,
            ]);

            $data = json_decode($response->getBody(), true);
            return isset($data['choices'][0]['message']['content'])
                ? trim($data['choices'][0]['message']['content'])
                : null;

        } catch (\Throwable $e) {
            Yii::error(['ai_reply_error' => $e->getMessage()], __METHOD__);
            return null;
        }
    }

    /**
     * Загружает базу знаний из файла
     */
    private function loadKnowledgeBase(): string
    {
        return Yii::$app->settings->get('openAi_knowledgeBase');
    }
}