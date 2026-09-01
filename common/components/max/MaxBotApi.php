<?php

namespace common\components\max;

use yii\base\Component;

/**
 * Минимальный клиент MAX Bot API для уведомлений и webhook поддержки.
 */
class MaxBotApi extends Component
{
    private const API_BASE_URL = 'https://platform-api2.max.ru';

    private MaxSupportSettings $settings;

    public function init(): void
    {
        parent::init();
        $this->settings = new MaxSupportSettings();
    }

    public function isConfigured(): bool
    {
        return $this->settings->isEnabled()
            && $this->settings->accessToken() !== ''
            && $this->settings->chatId() !== '';
    }

    /**
     * @param array<int, array{text: string, payload: string}> $buttons
     */
    public function sendSupportMessage(string $text, array $buttons = [], ?string $imageUrl = null): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        return $this->sendMessage($this->settings->chatId(), $text, $buttons, $imageUrl);
    }

    /**
     * @param int|string $chatId
     * @param array<int, array{text: string, payload: string}> $buttons
     */
    public function sendMessage($chatId, string $text, array $buttons = [], ?string $imageUrl = null): array
    {
        $body = self::messageBody($text, $buttons, $imageUrl);

        return $this->request('POST', '/messages', ['chat_id' => (string)$chatId], $body);
    }

    /**
     * @param array<int, array{text: string, payload: string}> $buttons
     */
    public static function messageBody(string $text, array $buttons = [], ?string $imageUrl = null): array
    {
        $body = ['text' => mb_substr($text, 0, 4000)];
        $attachments = [];
        if ($imageUrl !== null && $imageUrl !== '') {
            $attachments[] = [
                'type' => 'image',
                'payload' => ['url' => $imageUrl],
            ];
        }
        if ($buttons !== []) {
            $row = [];
            foreach ($buttons as $button) {
                if (empty($button['text']) || empty($button['payload'])) {
                    continue;
                }
                $row[] = [
                    'type' => 'callback',
                    'text' => mb_substr((string)$button['text'], 0, 128),
                    'payload' => mb_substr((string)$button['payload'], 0, 1024),
                ];
            }
            if ($row !== []) {
                $attachments[] = [
                    'type' => 'inline_keyboard',
                    'payload' => ['buttons' => [$row]],
                ];
            }
        }
        if ($attachments !== []) {
            $body['attachments'] = $attachments;
        }

        return $body;
    }

    public function answerCallback(string $callbackId, string $notification): array
    {
        if ($callbackId === '') {
            return [];
        }

        return $this->request(
            'POST',
            '/answers',
            ['callback_id' => $callbackId],
            ['notification' => mb_substr($notification, 0, 200)]
        );
    }

    public function getSubscriptions(): array
    {
        return $this->request('GET', '/subscriptions');
    }

    public function createSubscription(string $url, string $secret): array
    {
        return $this->request('POST', '/subscriptions', [], [
            'url' => $url,
            'update_types' => ['bot_added', 'message_created', 'message_callback'],
            'secret' => $secret,
        ]);
    }

    /**
     * Регистрирует или обновляет webhook текущего токена.
     */
    public function ensureSupportWebhook(): array
    {
        if (!$this->settings->isEnabled()) {
            return ['status' => 'disabled'];
        }
        if ($this->settings->accessToken() === '') {
            throw new \RuntimeException('Не заполнен access token MAX.');
        }
        $url = $this->settings->supportWebhookUrl();
        if ($url === '') {
            throw new \RuntimeException('Не заполнен params[apiPublicUrl] для URL webhook MAX.');
        }
        $urlPort = parse_url($url, PHP_URL_PORT);
        if (strpos($url, 'https://') !== 0 || ($urlPort !== null && (int)$urlPort !== 443)) {
            throw new \RuntimeException('Webhook MAX должен быть доступен по HTTPS на порту 443.');
        }

        $secret = $this->settings->webhookSecret();
        if (!preg_match('/^[A-Za-z0-9_-]{5,256}$/', $secret)) {
            throw new \RuntimeException(
                'Секрет webhook MAX должен содержать 5–256 латинских букв, цифр, дефисов или подчёркиваний.'
            );
        }

        $subscriptions = $this->getSubscriptions();
        $alreadyExists = false;
        foreach ($this->subscriptionItems($subscriptions) as $subscription) {
            if ((string)($subscription['url'] ?? '') === $url) {
                $alreadyExists = true;
                break;
            }
        }

        // MAX обновляет существующую подписку тем же POST: это применяет новый secret
        // и гарантирует наличие всех нужных типов событий.
        $result = $this->createSubscription($url, $secret);

        return [
            'status' => $alreadyExists ? 'updated' : 'created',
            'url' => $url,
            'result' => $result,
        ];
    }

    /**
     * @return array<int, array>
     */
    private function subscriptionItems(array $response): array
    {
        foreach (['subscriptions', 'items', 'data'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values(array_filter($response[$key], 'is_array'));
            }
        }

        $isList = array_keys($response) === range(0, count($response) - 1);

        return $isList ? array_values(array_filter($response, 'is_array')) : [];
    }

    private function request(string $method, string $path, array $query = [], array $body = []): array
    {
        $token = $this->settings->accessToken();
        if ($token === '') {
            throw new \RuntimeException('MAX Bot API access token is empty.');
        }

        $url = self::API_BASE_URL . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $curl = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            // MAX всегда вызывается напрямую, независимо от системных proxy-переменных.
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
        ];
        if ($body !== []) {
            $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonBody === false) {
                throw new \RuntimeException('MAX Bot API JSON encoding failed: ' . json_last_error_msg());
            }
            $options[CURLOPT_POSTFIELDS] = $jsonBody;
        }
        curl_setopt_array($curl, $options);

        $raw = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($raw === false) {
            throw new \RuntimeException('MAX Bot API request failed: ' . $curlError);
        }

        $decoded = json_decode((string)$raw, true);
        $decoded = is_array($decoded) ? $decoded : [];
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $decoded['message'] ?? $decoded['error'] ?? ('HTTP ' . $httpCode);
            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }
            throw new \RuntimeException('MAX Bot API error: ' . $message);
        }
        if (array_key_exists('success', $decoded) && !$decoded['success']) {
            throw new \RuntimeException(
                'MAX Bot API error: ' . (string)($decoded['message'] ?? 'operation failed')
            );
        }

        return $decoded;
    }
}
