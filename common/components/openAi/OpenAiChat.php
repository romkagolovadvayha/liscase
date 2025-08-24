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
    public function getReply(string $chatLog): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' =>
                    "Ты модератор чата. Вход — это JSON с ключом `messages`.\n".
                    "Верни ТОЛЬКО JSON-объект вида {\"items\": [...]} без Markdown и пояснений.\n".
                    "Элемент items: {\"steam_id\": string, \"type\": integer, \"message\": string}.\n".
                    "Типы: 1 — оскорбление родителей; 2 — оскорбление администрации; 3 — просьба помощи админа.\n".
                    "Если нарушений нет — {\"items\": []}."
            ],
            // Отдай чистый JSON, без текста перед ним
            ['role' => 'user', 'content' => $chatLog],
        ];

        $response = $this->client->post('chat/completions', [
            'json' => [
                'model' => Yii::$app->settings->get('openAi_model'),
                'messages' => $messages,
                'temperature' => 0.0,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'Violations',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['items'],
                            'properties' => [
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'additionalProperties' => false,
                                        'required' => ['steam_id','type','message'],
                                        'properties' => [
                                            'steam_id' => ['type' => 'string'],
                                            'type'     => ['type' => 'integer', 'enum' => [1,2,3]],
                                            'message'  => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'timeout' => 20,
        ]);

        $data = json_decode($response->getBody(), true);
        $content = $data['choices'][0]['message']['content'] ?? '{"items":[]}';

        $decoded = json_decode($content, true);
        if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
            return [];
        }

        $clean = [];
        foreach ($decoded['items'] as $row) {
            if (!is_array($row)) continue;
            $steam_id = isset($row['steam_id']) ? trim((string)$row['steam_id']) : '';
            $type     = isset($row['type']) ? (int)$row['type'] : 0;
            $message  = isset($row['message']) ? trim((string)$row['message']) : '';
            if ($steam_id !== '' && in_array($type, [1,2,3], true) && $message !== '') {
                $clean[] = compact('steam_id','type','message');
            }
        }
        return $clean;
    }


}