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
                    'Ты модератор. Вход — JSON {"messages":[{"steam_id":string,"message":string},...]}.
Верни ТОЛЬКО {"items":[...]}.

Правила классификации:
- type=1 (оскорбление родителей) — ТОЛЬКО если сообщение содержит явное упоминание родителей + оскорбление
  из списка (регистр/опечатки игнорировать): ["мать","маму","мамка","матерь","батя","отец",
  "папа","папик","родитель","родителей","родаков","родоков"].
  Просто общий мат ("даун","хуесос","ебаный" и т.п.) БЕЗ этих слов — НЕ type=1.
- type=2 — оскорбление администрации: ТОЛЬКО если сообщение содержит явное упоминание ["админ","админы","модер","модератор"] + оскорбление.
- type=3 — просьба помощи админу: ТОЛЬКО если сообщение содержит явное упоминание ["админ","админы","модер"] рядом с "помоги","помощь","help",
  "как связаться","куда жалобу","репорт","f7" и т.п.

Ты должен быть уверен на 100%, что игрок нарушил!
Если ни одно правило не сработало — не добавляй элемент.
Если совпало несколько — приоритет: 1 > 2 > 3.

Вывод строго по схеме:
items: [{steam_id:string, type:1|2|3, message:string}]
Без Markdown и лишнего текста.'
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