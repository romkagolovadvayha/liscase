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
     * @param bool   $useDiscordInstructions Использовать инструкции для Discord
     *
     * @return string|null
     */
    public function getReply(
        string $userMessage,
        string $username,
        string $server,
        array $chatHistory = [],
        ?int $ticketId = null,
        $user = null,
        bool $useDiscordInstructions = false,
        array $imageUrls = []
    ): ?string {
        try {
            $knowledge = $this->loadKnowledgeBase();

            // Контекст (не инструкции!)
            $context = [];
            $context[] = "Ник игрока: " . htmlspecialchars($username);
            $context[] = "Сервер: " . trim((string)$server);

            // Пытаемся получить информацию о серверах (может быть ошибка)
            try {
                $p = new PersonalBotSystem();
                
                $context[] = "Как подключиться к серверу?";
                $context[] = "Подключение через консоль F1. Список IP серверов:";
                $ipInfo = $p->getIp();
                if (!empty($ipInfo)) {
                    // Удаляем HTML теги для чистого текста
                    $ipInfo = strip_tags($ipInfo);
                    $ipInfo = html_entity_decode($ipInfo, ENT_QUOTES, 'UTF-8');
                    if (!empty(trim($ipInfo))) {
                        $context[] = trim($ipInfo);
                    }
                }
                
                $context[] = "Когда вайп?";
                $context[] = "Даты вайпов на серверах:";
                $wipeInfo = $p->getWipe();
                if (!empty($wipeInfo)) {
                    // Удаляем HTML теги для чистого текста
                    $wipeInfo = strip_tags($wipeInfo);
                    $wipeInfo = html_entity_decode($wipeInfo, ENT_QUOTES, 'UTF-8');
                    if (!empty(trim($wipeInfo))) {
                        $context[] = trim($wipeInfo);
                    }
                }
            } catch (\Throwable $e) {
                // Если не удалось получить информацию о серверах - пропускаем
                Yii::warning('OpenAiSupport: не удалось получить информацию о серверах: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine(), __METHOD__);
            }

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
                $context[] = "Публичный номер этого тикета: {$ticketId}. Открыть тикет на сайте: https://prostoj.store/support/ticket?id={$ticketId}";
            }

            // Статические пути разделов сайта (Next.js prostoj-frontend) — чтобы ответы ссылались на актуальные URL
            $context[] = 'Разделы сайта https://prostoj.store (пути): магазин /store; профиль /profile; история /profile/history; рефералка /profile/referral и страница /referral; скины и trade /skindrops; поддержка /support; кланы /clans; карты /maps/<тег_сервера>; кастомные скины /custom-skins; задания /tasks; статистика /stats; серверы /servers; рейды /raid-table; банлист /banlist; правила /rules; новости /posts; календарь вайпов /wipe-calendar.';

            // Если нужно логировать контекст — делай это осознанно
            // Yii::$app->telegramChats->sendMessage(implode("\n", $context));

            $messages = [];

            // Инструкции — только в system
            if ($useDiscordInstructions) {
                $systemInstructions = Yii::$app->settings->get('openAi_instructionsDiscord');
                if (empty($systemInstructions)) {
                    // Если инструкции для Discord не настроены, используем обычные
                    $systemInstructions = Yii::$app->settings->get('openAi_instructions');
                }
            } else {
                $systemInstructions = Yii::$app->settings->get('openAi_instructions');
            }
            
            $systemInstructions .= "\n\nВажно: отвечай игроку по сути обращения в тикете поддержки естественным языком. Без JSON и техничных форматов, без обращений к разработчикам. Коротко и по делу.";
            $systemInstructions .= "\nЕсли игрок прислал скриншот/изображение — внимательно посмотри содержимое. Если видно нарушение правил (читы, токсичность, оскорбления, багоюз, реклама и т.п.) — кратко подтверди, что скрин получен, нарушение понятно, примем меры / передадим модерации. Не выдумывай то, чего нет на картинке. Без длинных лекций.";

            $messages[] = ['role' => 'system', 'content' => $systemInstructions];

            // Подкладываем базу знаний и динамический контекст единым блоком как user
            $messages[] = [
                'role' => 'user',
                'content' =>
                    "Контекст (справка для ответа, не обязательно упоминать явно):\n"
                    . trim($knowledge) . "\n\n"
                    . implode("\n", $context)
            ];

            $historyItems = $chatHistory;
            $currentImages = array_values(array_filter($imageUrls));

            // История уже содержит текущее сообщение игрока — снимаем его, чтобы не дублировать,
            // и забираем картинки в финальный multimodal-блок.
            if (!empty($historyItems)) {
                $last = $historyItems[count($historyItems) - 1];
                if (isset($last['user']) && !isset($last['bot'])) {
                    $lastText = trim((string)$last['user']);
                    $currentText = trim($userMessage);
                    if ($currentText === '' || $lastText === $currentText) {
                        if (empty($currentImages) && !empty($last['images']) && is_array($last['images'])) {
                            $currentImages = $last['images'];
                        }
                        if ($currentText === '' && $lastText !== '') {
                            $userMessage = $lastText;
                        }
                        array_pop($historyItems);
                    }
                }
            }

            foreach ($historyItems as $item) {
                if (!empty($item['user']) || !empty($item['images'])) {
                    $messages[] = [
                        'role' => 'user',
                        'content' => $this->buildUserContent(
                            (string)($item['user'] ?? ''),
                            is_array($item['images'] ?? null) ? $item['images'] : []
                        ),
                    ];
                }
                if (!empty($item['bot'])) {
                    $messages[] = ['role' => 'assistant', 'content' => (string)$item['bot']];
                }
            }

            $finalText = trim($userMessage);
            if ($finalText === '' && !empty($currentImages)) {
                $finalText = 'Пользователь отправил скриншот.';
            }

            $messages[] = [
                'role' => 'user',
                'content' => $this->buildUserContent($finalText, $currentImages),
            ];

            $model = (string)Yii::$app->settings->get('openAi_model');
            $payload = [
                'model' => $model,
                'messages' => $messages,
            ];

            // GPT-5 / o-series: max_tokens и custom temperature не поддерживаются
            if ($this->isNewCompletionsModel($model)) {
                // Бюджет включает reasoning-токены — 350 часто даёт пустой ответ
                $payload['max_completion_tokens'] = 2000;
            } else {
                $payload['temperature'] = $this->temperature ?? 0.7;
                $payload['max_tokens'] = 350;
            }

            $hasImages = !empty($currentImages);
            if (!$hasImages) {
                foreach ($historyItems as $item) {
                    if (!empty($item['images'])) {
                        $hasImages = true;
                        break;
                    }
                }
            }

            $response = $this->client->post('chat/completions', [
                'json' => $payload,
                'timeout' => $hasImages ? 90 : 60,
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
     * Текст или multimodal-контент (текст + картинки) для Chat Completions.
     *
     * @param string[] $imageUrls
     * @return string|array
     */
    private function buildUserContent(string $text, array $imageUrls)
    {
        $imageUrls = array_slice(array_values(array_filter($imageUrls)), 0, 4);
        if (empty($imageUrls)) {
            return $text;
        }

        $parts = [];
        if (trim($text) !== '') {
            $parts[] = ['type' => 'text', 'text' => $text];
        }
        foreach ($imageUrls as $url) {
            $parts[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $url,
                    'detail' => 'low',
                ],
            ];
        }

        return $parts;
    }

    /**
     * Загружает базу знаний из файла
     */
    private function loadKnowledgeBase(): string
    {
        return Yii::$app->settings->get('openAi_knowledgeBase');
    }

    /**
     * GPT-5 / o-series принимают max_completion_tokens и не принимают custom temperature.
     */
    private function isNewCompletionsModel(string $model): bool
    {
        $model = strtolower(trim($model));

        return (bool)preg_match('/^(o\d|gpt-5)/', $model);
    }
}