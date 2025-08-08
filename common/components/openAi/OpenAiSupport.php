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
    public function getReply(string $userMessage, array $chatHistory = [], $username, $server, $ticketId = null, $user = null): ?string
    {
        $knowledge = $this->loadKnowledgeBase();

        $p = new PersonalBotSystem();
        $knowledge .= "\n\nНик игрока который пишет: " . htmlspecialchars($username);
        $knowledge .= "\n\nСервер на котором играет игрок: " . $server;
        $knowledge .= "\n\nКак подключиться к серверу?";
        $knowledge .= "\nПодключиться можно через консоль F1, Вот список IP серверов:\n";
        $knowledge .= $p->getIp();
        $knowledge .= "\n\nКогда вайп?";
        $knowledge .= "\nВот даты вайпов на серверах:\n";
        if (!empty($user)) {
            /** @var Reports[] $reports */
            $reports = Reports::find()
                              ->andWhere(['steam_id' => $user->steam_id])
                              ->orderBy(['id' => SORT_DESC])
                              ->limit(3)
                              ->all();
            if (empty($reports)) {
                $knowledge .= "Игрок отправлял ни одной жалобы на игроков! Если он жалуется на игрока, он может в том числе отправить жалобу нажмав кнопку {PARAM_COMMAND_F7} в игре.\n";
            } else {
                $knowledge .= 'Если игрок жалуется, но не сказал на кого, ты можешь догадаться сам судя по его последним жалобам на сервере: ';
                foreach ($reports as $item) {
                    $usernameitem = htmlspecialchars($item->user->username);
                    $reason = htmlspecialchars($item->reason);
                    $knowledge .= "{$usernameitem} (steam_id: {$item->user->steam_id}; причина: {$reason}; дата: {$item->created_at}); ";
                }
                $knowledge .= "\n";
            }
            if (!empty($user->userProfile->trade_link)) {
                $knowledge .= "\nТрейд ссылка стим игрока: {$user->userProfile->trade_link}";
            }
        }
        if (!empty($ticketId)) {
            $knowledge .= "\nСсылка на закрытие тикета: <a href=\"https://prostoj.store/support/ticket-close?id={$ticketId}\">Закрыть тикет</a>\n";
        }
        $knowledge .= $p->getWipe();

        Yii::$app->telegramChats->sendMessage($knowledge);

        $messages = [];

        // Вставляем system-инструкцию
        $messages[] = [
            'role' => 'system',
            'content' => Yii::$app->settings->get('openAi_instructions') . "\n\nИспользуй только приведённые ниже инструкции:\n\n" . $knowledge,
        ];

        // Подключаем историю переписки (если передана)
        foreach ($chatHistory as $item) {
            if (!empty($item['user'])) {
                $messages[] = ['role' => 'user', 'content' => $item['user']];
            }
            if (!empty($item['bot'])) {
                $messages[] = ['role' => 'assistant', 'content' => $item['bot']];
            }
        }

        // Отправляем запрос в OpenAI
        $response = $this->client->post('chat/completions', [
            'json' => [
                'model' => Yii::$app->settings->get('openAi_model'),
                'messages' => $messages,
                'temperature' => $this->temperature,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Загружает базу знаний из файла
     */
    private function loadKnowledgeBase(): string
    {
        return Yii::$app->settings->get('openAi_knowledgeBase');
    }
}