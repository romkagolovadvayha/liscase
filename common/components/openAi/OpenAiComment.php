<?php

namespace common\components\openAi;

use common\components\proxy\ProxySettings;
use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\user\User;
use GuzzleHttp\Client;
use Yii;

class OpenAiComment extends \yii\base\Component
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

        ProxySettings::applyToGuzzleOptions($clientConfig, ProxySettings::OPENAI_COMMENT);

        $this->client = new Client($clientConfig);
    }

    /**
     * Сгенерировать комментарий к статье: новый или ответ на конкретный комментарий
     *
     * @param string   $postTitle
     * @param string   $postContent
     * @param bool     $isReplyOnComment    Генерировать ответ на комментарий?
     * @param array    $commentHistory      Массив вида [['id'=>int,'content'=>string], ...]
     * @param int|null $replyToCommentId    К какому комментарию отвечаем (если ответ)
     *
     * @return array{content:string,parentId:int|null}|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getReply(
        string $postTitle,
        string $postContent,
        bool $isReplyOnComment,
        array $commentHistory = [],
        ?int $replyToCommentId = null
    ): ?array {
        if (!OpenAiSettings::isEnabled(OpenAiSettings::COMMENT)) {
            return null;
        }

        // Сжимаем контент статьи, чтобы не раздувать токены (опционально)
        $postContentShort = mb_substr(trim(strip_tags($postContent)), 0, 2000);

        // Строим промпт
        $prompt = "Заголовок: {$postTitle}\n"
            . "Краткое содержание:\n{$postContentShort}\n\n";

        if (!empty($commentHistory)) {
            $prompt .= "Существующие комментарии (id → текст):\n";
            // Не больше 10 для экономии токенов
            $slice = array_slice($commentHistory, -10);
            foreach ($slice as $c) {
                $id = (int)($c['id'] ?? 0);
                $tx = trim((string)($c['content'] ?? ''));
                if ($id && $tx !== '') {
                    $prompt .= "- ({$id}) {$tx}\n";
                }
            }
            $prompt .= "\n";
        }

        if ($isReplyOnComment && $replyToCommentId) {
            $prompt .= "Задача: Напиши короткий человеческий ответ на комментарий с id={$replyToCommentId}.\n";
            $prompt .= "Стиль: естественный, разговорный, без токсичности и шаблонов, 1–2 предложения.\n";
        } else {
            $prompt .= "Задача: Напиши короткий человеческий комментарий к статье (не ответ), 1–2 предложения.\n";
            $prompt .= "Стиль: естественный, разговорный, без штампов и токсичности.\n";
        }

        // Схема JSON-ответа
        $jsonSchema = [
            'name' => 'CommentReply',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['content','parentId'],
                'properties' => [
                    'content' => [
                        'type' => 'string',
                        'minLength' => 2,
                        'maxLength' => 350
                    ],
                    'parentId' => [
                        // Разрешаем null, если это новый комментарий
                        'anyOf' => [
                            ['type' => 'integer'],
                            ['type' => 'null']
                        ]
                    ],
                ],
            ],
        ];

        // Сообщения
        $messages = [
            [
                'role' => 'system',
                'content' => (Yii::$app->settings->get('openAi_comments') ?: "")
                    . "\nОтвечай только валидным JSON по схеме. Никакого лишнего текста."
            ],
            ['role' => 'user', 'content' => $prompt],
        ];

        $response = $this->client->post('chat/completions', [
            'json' => [
                'model' => Yii::$app->settings->get('openAi_model'),
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 200,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $jsonSchema,
                ],
            ],
            'timeout' => 20,
        ]);

        $data = json_decode($response->getBody(), true);
        $raw = $data['choices'][0]['message']['content'] ?? null;
        if (!$raw) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['content']) || !array_key_exists('parentId', $decoded)) {
            return null;
        }

        // Жёстко фиксируем parentId: если это ответ — должен быть именно $replyToCommentId
        if ($isReplyOnComment) {
            $decoded['parentId'] = $replyToCommentId ?: null;
        } else {
            $decoded['parentId'] = null;
        }

        // Санитайз + обрезка
        $content = trim((string)$decoded['content']);
        $content = mb_substr($content, 0, 350);

        if ($content === '') {
            return null;
        }

        return [
            'content'  => $content,
            'parentId' => $decoded['parentId'],
        ];
    }


}
