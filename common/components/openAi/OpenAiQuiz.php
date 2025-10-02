<?php
namespace common\components\openAi;

use GuzzleHttp\Client;
use Yii;

class OpenAiQuiz extends \yii\base\Component
{
    /** @var float */
    public $temperature = 0.2;

    /** @var Client */
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
            'timeout'  => 20,
        ];

        // Прокси (если задан)
        $proxyIp = Yii::$app->settings->get('proxy_ip');
        if (!empty($proxyIp)) {
            $proxy = 'http://' .
                Yii::$app->settings->get('proxy_username') . ':' .
                Yii::$app->settings->get('proxy_password') . '@' .
                $proxyIp;
            $clientConfig['proxy'] = ['http' => $proxy, 'https' => $proxy];
        }

        $this->client = new Client($clientConfig);
    }

    /**
     * Генерирует N вопросов исходя из базы знаний openAi_server_quiz.
     * Возвращает МАССИВ вопросов в формате плагина:
     *   [ ['text'=>string, 'options'=>[4]string, 'correct'=>int 0..3], ... ]
     */
    public function questions(int $count = 20): array
    {
        $count = max(1, min(50, (int)$count));

        $knowledge = (string)Yii::$app->settings->get('openAi_server_quiz');
        $model     = 'gpt-4o-mini';

        // SYSTEM: жёстко просим РОВНО 4 варианта с указанием правильного индекса 0..3
        $system = <<<SYS
Ты генератор вопросов викторины по игре Rust. Используй ТОЛЬКО факты из базы знаний ниже.
Сформируй разнообразные вопросы (смешивай темы: ресурсы, крафт, хп объектов, транспорт, карты, верстаки, время, т.п.).
Для каждого вопроса верни РОВНО 4 варианта ответов (строки), из них РОВНО ОДИН правильный.
"correct" — это индекс правильного варианта в массиве options, начиная с 0.
Не повторяй вопросы. Не придумывай факты вне базы знаний.
SYS;

        $user = <<<USR
База знаний:
----------------
{$knowledge}
----------------
Нужно сгенерировать {$count} вопросов. Формат ответа строго по JSON-схеме.
USR;

        // Строгая JSON-схема под плагин
        $jsonSchema = [
            'name'   => 'RustQuizPacket',
            'strict' => true,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'required'             => ['questions'],
                'properties'           => [
                    'questions' => [
                        'type'  => 'array',
                        'items' => [
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'required'             => ['text', 'options', 'correct'],
                            'properties'           => [
                                'text'    => ['type' => 'string', 'minLength' => 1],
                                'options' => [
                                    'type'  => 'array',
                                    'minItems' => 4,
                                    'maxItems' => 4,
                                    'items' => ['type' => 'string', 'minLength' => 1],
                                ],
                                'correct' => [
                                    'type' => 'integer',
                                    'minimum' => 0,
                                    'maximum' => 3
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user',   'content' => $user],
                    ],
                    'temperature'     => $this->temperature,
                    'response_format' => [
                        'type'        => 'json_schema',
                        'json_schema' => $jsonSchema,
                    ],
                ],
            ]);

            $data    = json_decode((string)$response->getBody(), true);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return $this->fallback($count);
            }

            $decoded = json_decode($content, true);
            if (!is_array($decoded) || !isset($decoded['questions']) || !is_array($decoded['questions'])) {
                return $this->fallback($count);
            }

            $clean = $this->validateAndNormalize($decoded['questions'], $count);
            if (empty($clean)) {
                return $this->fallback($count);
            }

            return $clean;
        } catch (\Throwable $e) {
            Yii::error('OpenAiQuiz error: ' . $e->getMessage(), __METHOD__);
            return $this->fallback($count);
        }
    }

    /**
     * Жёсткая валидация + обрезка по количеству
     */
    private function validateAndNormalize(array $items, int $limit): array
    {
        $out = [];
        foreach ($items as $q) {
            if (!is_array($q)) continue;

            $text    = isset($q['text']) ? trim((string)$q['text']) : '';
            $options = isset($q['options']) && is_array($q['options']) ? $q['options'] : null;
            $correct = isset($q['correct']) ? (int)$q['correct'] : null;

            if ($text === '' || $options === null || count($options) !== 4 || $correct === null) continue;

            // трим и фильтр пустых строк
            $opts = [];
            foreach ($options as $o) {
                $o = trim((string)$o);
                if ($o === '') { $opts = []; break; }
                $opts[] = $o;
            }
            if (count($opts) !== 4) continue;

            // защита индекса
            if ($correct < 0 || $correct > 3) continue;

            $out[] = [
                'text'    => $text,
                'options' => array_values($opts),
                'correct' => $correct,
            ];

            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /**
     * Фолбэк — если OpenAI не ответил/вернул невалидный JSON.
     * Здесь можно держать свой набор "дежурных" вопросов (или собрать из settings).
     */
    private function fallback(int $count): array
    {
        // Минимальный набор (пример). Можешь заменить на свой статичный список.
        $pool = [
            ['Сколько ткани нужно на спальный мешок?', ['20','30','50','75'], 1],
            ['Что даёт больше серы с ноды?', ['Кирка','Ледоруб','Jackhammer','Камень'], 2],
            ['Сколько слотов в большом ящике?', ['24','30','36','42'], 2],
            ['Сколько требуется HQM на Т3 верстак?', ['10','20','25','30'], 1],
            ['Сколько металла на кодовый замок?', ['50','75','100','125'], 1],
            ['Сколько мест в моторной лодке?', ['2','3','4','5'], 2],
            ['Порох в 1 патроне 5.56?', ['10','15','20','25'], 1],
            ['Из чего крафтится низкосортное топливо?', ['Жир животных','Ткань','Металл фр.','Уголь'], 0],
        ];

        shuffle($pool);
        $pool = array_slice($pool, 0, max(1, min($count, count($pool))));

        $res = [];
        foreach ($pool as [$t, $opts, $c]) {
            $res[] = ['text' => $t, 'options' => $opts, 'correct' => (int)$c];
        }
        return $res;
    }
}
