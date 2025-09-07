<?php
namespace common\components\telegram;

use yii\base\Component;
use yii\httpclient\Client;

class TelegramBot extends Component
{
    private function api(string $m): string {
        $token = \Yii::$app->settings->get('telegram_parser_bot_token');
        return "https://api.telegram.org/bot{$token}/{$m}";
    }

    private function isLocalFile($v): bool
    {
        return is_string($v) && $v !== '' && is_file($v);
    }

    public function call(string $method, array $params = [])
    {
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $req = $client->createRequest()
                      ->setMethod('POST')
                      ->setUrl($this->api($method))
                      ->setData($params);

        $resp = $req->send();

        if (!$resp->isOk) {
            $body = $resp->getContent();
            $desc = null;
            if ($body) {
                $j = json_decode($body, true);
                if (is_array($j) && isset($j['description'])) $desc = $j['description'];
            }
            $tail = $desc ? " ({$desc})" : (is_string($body) ? (': '.mb_strimwidth($body,0,300,'…')) : '');
            throw new \RuntimeException("HTTP {$resp->statusCode}{$tail}");
        }

        $data = $resp->getData();
        if (empty($data['ok'])) {
            throw new \RuntimeException('Telegram error: '.json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        return $data['result'];
    }

    public function callMultipart(string $method, array $params, array $files /* ['field' => string path | \CURLFile] */)
    {
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        $req = $client->createRequest()
                      ->setMethod('POST')
                      ->setUrl($this->api($method))
                      ->setData($params);

        foreach ($files as $field => $file) {
            if ($file instanceof \CURLFile) {
                $req->addFile(
                    $field,
                    $file->getFilename(),
                    $file->getPostFilename() ?: basename($file->getFilename()),
                    $file->getMimeType() ?: null
                );
            } elseif (is_string($file) && is_file($file)) {
                $req->addFile($field, $file);
            } else {
                throw new \InvalidArgumentException("Invalid file for field '{$field}'");
            }
        }

        $resp = $req->send();

        if (!$resp->isOk) {
            $body = $resp->getContent();
            $desc = null;
            if ($body) {
                $j = json_decode($body, true);
                if (is_array($j) && isset($j['description'])) $desc = $j['description'];
            }
            $tail = $desc ? " ({$desc})" : (is_string($body) ? (': '.mb_strimwidth($body,0,300,'…')) : '');
            throw new \RuntimeException("Telegram API HTTP {$resp->statusCode}{$tail}");
        }

        $data = $resp->getData();
        if (empty($data['ok'])) {
            throw new \RuntimeException('Telegram API error: '.json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        return $data['result'];
    }

    public function copyMessage($chatId, $fromChatId, $messageId, $caption = null)
    {
        $p = ['chat_id'=>$chatId,'from_chat_id'=>$fromChatId,'message_id'=>$messageId];
        if ($caption !== null) $p['caption'] = $caption;
        return $this->call('copyMessage', $p);
    }

    public function forwardMessage($chatId, $fromChatId, $messageId)
    {
        return $this->call('forwardMessage', ['chat_id'=>$chatId,'from_chat_id'=>$fromChatId,'message_id'=>$messageId]);
    }

    public function sendMessage($chatId, string $text, array $extra = [])
    {
        return $this->call('sendMessage', ['chat_id'=>$chatId,'text'=>$text] + $extra);
    }

    /**
     * $photo может быть:
     * - локальный путь => multipart
     * - \CURLFile       => multipart
     * - "attach://field" + $extra['_file_map'][field] => multipart
     * - file_id | http(s) URL => Telegram сам скачает (не рекомендуется)
     */
    public function sendPhoto($chatId, $photo, ?string $caption = null, array $extra = [])
    {
        $p = ['chat_id'=>$chatId] + $extra;
        if ($caption !== null) $p['caption'] = $caption;

        $p['photo'] = $photo;
        print_r($p);
        return $this->call('sendPhoto', $p);
    }

    public function sendVideo($chatId, $video, ?string $caption = null, array $extra = [])
    {
        // Telegram любит supports_streaming для mp4
        $p = ['chat_id'=>$chatId, 'supports_streaming'=>true] + $extra;
        if ($caption !== null) $p['caption'] = $caption;

        if (is_string($video) && str_starts_with($video, 'attach://')) {
            $field = substr($video, strlen('attach://'));
            $p['video'] = $video;
            $file = $extra['_file_map'][$field] ?? null;
            if (!$file) throw new \InvalidArgumentException("attach file map for '{$field}' missing");
            return $this->callMultipart('sendVideo', $p, [$field => $file]);
        }

        if ($this->isLocalFile($video) || $video instanceof \CURLFile) {
            return $this->callMultipart('sendVideo', $p, ['video' => $video]);
        }

        $p['video'] = $video; // file_id / URL
        return $this->call('sendVideo', $p);
    }

    /**
     * $media: элементы с media="attach://fileX", type="photo|video"
     * $files: ['fileX' => '/path/to/file' | \CURLFile]
     */
    public function sendMediaGroup($chatId, array $media, array $files = [])
    {
        $params = [
            'chat_id' => $chatId,
            'media'   => json_encode($media, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
        if ($files) {
            return $this->callMultipart('sendMediaGroup', $params, $files);
        }
        return $this->call('sendMediaGroup', $params);
    }
}
