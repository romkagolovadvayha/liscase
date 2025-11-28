<?php

namespace common\components\openAi;

use GuzzleHttp\Client;
use Yii;

class OpenAiVkPost extends \yii\base\Component
{
    public $temperature = 0.7;

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
     * Обработать статью для публикации в ВКонтакте
     *
     * @param string $title Заголовок статьи
     * @param string $content Содержание статьи
     * @param string|null $description Описание статьи (опционально)
     *
     * @return string|null Обработанный текст для ВК или null в случае ошибки
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processForVk(string $title, string $content, ?string $description = null): ?string
    {
        // Очищаем HTML и обрезаем контент для экономии токенов
        $cleanContent = strip_tags($content);
        $cleanContent = preg_replace('/\s+/', ' ', $cleanContent);
        $cleanContent = trim($cleanContent);
        
        // Ограничиваем длину контента (максимум 3000 символов)
        if (mb_strlen($cleanContent) > 3000) {
            $cleanContent = mb_substr($cleanContent, 0, 3000) . '...';
        }

        // Формируем промпт
        $prompt = "Заголовок статьи: {$title}\n\n";
        
        if (!empty($description)) {
            $cleanDescription = strip_tags($description);
            $prompt .= "Описание: {$cleanDescription}\n\n";
        }
        
              $prompt .= "Содержание статьи:\n{$cleanContent}\n\n";
              $prompt .= "Напиши кратко на тематику игры Rust статью для поста ВКонтакте без изображений, используй смайлы. ";
              $prompt .= "В конце добавь 3-5 релевантных хештегов, связанных с темой статьи.";
              $prompt .= "Статья дожна быть не более 800 символов.";
              $prompt .= "Не нужно делать текст выделенным жирным, курсивом, кодом и ссылками.";

        // Сообщения для OpenAI
              $messages = [
                  [
                      'role' => 'system',
                      'content' => 'Ты SMM-копирайтер, создающий посты для ВКонтакте. Используй смайлы и эмодзи. Всегда добавляй в конце поста 3-5 релевантных хештегов, связанных с темой поста.'
                  ],
                  ['role' => 'user', 'content' => $prompt],
              ];

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => Yii::$app->settings->get('openAi_model') ?: 'gpt-3.5-turbo',
                    'messages' => $messages,
                    'temperature' => $this->temperature,
                    'max_tokens' => 800,
                ],
                'timeout' => 30,
            ]);

            $data = json_decode($response->getBody(), true);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (empty($content)) {
                Yii::error("OpenAI VK Post: Empty response", __METHOD__);
                return null;
            }

            // Очищаем и возвращаем результат
            $content = trim($content);
            
            // Удаляем возможные кавычки в начале и конце
            $content = preg_replace('/^["\']|["\']$/', '', $content);
            
            return $content;
        } catch (\Exception $e) {
            Yii::error("OpenAI VK Post error: " . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}
