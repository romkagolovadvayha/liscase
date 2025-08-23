<?php

namespace common\components\openAi;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\user\User;
use GuzzleHttp\Client;
use Yii;

class OpenAiChat extends \yii\base\Component
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
     *
     * @return string|null
     */
    public function getReply(string $userMessage): ?string
    {
        $messages = [];

        // Вставляем system-инструкцию
        $messages[] = [
            'role' => 'system',
            'content' => 'проверь чат и дай в ответ json кто нарушил type = 1, если оскорбление родителей type = 2, если оскорбление администрации type = 3, если просит помощи админа пример ответа: [ { steam_id: 123, type: 2, message: "text" }, .... ]' . PHP_EOL . 'Вот сообщения пользователей: ' . PHP_EOL . $userMessage,
        ];

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