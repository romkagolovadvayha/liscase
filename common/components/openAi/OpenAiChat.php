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
                    'Ты модератор. Вход сообщения пользователей в общем чате между собой — JSON {"messages":[{"steam_id":string,"message":string},...]}.
                        Верни ТОЛЬКО {"items":[...]}.
                        
                        Это общий чат! Соощения могут поступать любому пользователю от любого польщователя. Ты должен понимать, что оскорбления не направлены в твой адрес, пока явно не скажут об этом!
                        
                        Классифицируй ТОЛЬКО по чётким признакам (игнорируй догадки, сарказм и контекст):
                        - type=1 (оскорбление родителей) — сообщение СОДЕРЖИТ слово о родителях из списка:
                          ["мать","мама","мамка","батя","отец","папа","родитель","родителей","родаков"] (любые формы, регистр/опечатки игнорировать)
                          И ОДНОВРЕМЕННО содержит оскорбление/сексуальную лексику в пределах ±20 символов от этого слова.
                          Примеры, которые ДА: "твою мать ебал", "мать шлюха", "у отца сосал".
                          Примеры, которые НЕТ: "не трогай моего парня", "ты ебанько".
                        - type=2 (оскорбление администрации) — есть ["админ","админы","модер","модератор"] (любые формы)
                          И рядом (±20 символов) есть оскорбление/брань.
                          "админы пидорасы" — ДА; "админ помоги" — НЕТ.
                        - type=3 повторяющиеся одинаковые сообщения более 3 от одного игрока - СПАМ.
                        - type=4 (просит помощи админа) — есть ["админ","админы","модер"] и рядом (±30 символов) слова
                          ["помоги","помощь","help","жалоб","репорт","f7","как связаться","куда жалобу"].
                          Если одновременно присутствует оскорбление администрации — отнести к type=2 (приоритет 2 > 4).
                        При совпадении нескольких правил приоритет: 1 > 2 > 3 > 4.
                        Если НИ одно правило не выполнено — НЕ добавляй элемент.
                        
                        если нет упоминания родителей или админа то игнорируй сообщение!
                        
                        Вывод строго: {"items":[{"steam_id":string,"type":1|2|3,"message":string}]}.
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
                                            'type'     => ['type' => 'integer', 'enum' => [1,2,3,4]],
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