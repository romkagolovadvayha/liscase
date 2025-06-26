<?php

namespace common\components\openAi;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\models\servers\Servers;
use GuzzleHttp\Client;
use Yii;

class OpenAiSupport extends \yii\base\Component
{
    public $model = 'gpt-4';
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
     */
    public function getReply(string $userMessage, array $chatHistory = []): ?string
    {
        $knowledge = $this->loadKnowledgeBase();

        $p = new PersonalBotSystem();
        $knowledge .= "\n\nКак подключиться к серверу?";
        $knowledge .= "\nПодключиться можно через консоль F1, Вот список IP серверов:\n";
        $knowledge .= $p->getIp();
        $knowledge .= "\n\nКогда вайп?";
        $knowledge .= "\nВот даты вайпов на серверах:\n";
        $knowledge .= $p->getWipe();

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
                'model' => $this->model,
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