<?php
namespace common\components\telegram;

use Yii;
use yii\base\Component;
use common\models\telegram\TelegramNews;

class TelegramNewsService extends Component
{
    /** @var TelegramBot|string */
    public $bot = 'telegram';
    public $allowedSourceChatIds = []; // <- из конфига

    public function init()
    {
        parent::init();
        $this->allowedSourceChatIds = array_filter(array_map('trim',
                                                             explode(',', Yii::$app->settings->get('telegram_parser_source_chat_ids') ?: '')
                                                   ));

        if (is_string($this->bot)) $this->bot = Yii::$app->get($this->bot);
    }

    public function createFromTelegramPost(array $post): TelegramNews
    {
        $sourceChatId = (string)($post['chat']['id'] ?? '');

        // фильтр источников
        if ($this->allowedSourceChatIds
            && !in_array($sourceChatId, $this->allowedSourceChatIds, true)) {
            throw new \DomainException('source_not_allowed');
        }
        $messageId    = (int)($post['message_id'] ?? 0);

        $existing = TelegramNews::find()->where([
                                                    'source_chat_id' => $sourceChatId,
                                                    'source_message_id' => $messageId,
                                                ])->one();
        if ($existing) return $existing;

        $model = new TelegramNews();
        $model->source_chat_id     = $sourceChatId;
        $model->source_message_id  = $messageId;
        $model->media_group_id     = $post['media_group_id'] ?? null;
        $model->text               = $post['text']    ?? null;
        $model->caption            = $post['caption'] ?? null;
        $model->content_type       = $this->detectType($post);
        $model->status             = TelegramNews::STATUS_NEW;
        $model->target_chat_id     = Yii::$app->settings->get('telegram_parser_target_chat_id') ?: null;
        $model->raw_json           = json_encode($post, JSON_UNESCAPED_UNICODE);
        $model->created_at         = time();
        $model->updated_at         = time();
        $model->save(false);

        return $model;
    }

    private function downloadToTemp(string $url, string $preferExt = '', ?string $refererUsername = null): string
    {
        $ch = curl_init($url);
        $headers = ['User-Agent: Mozilla/5.0 (compatible; TgPuller/1.2)'];
        if ($refererUsername) $headers[] = "Referer: https://t.me/s/{$refererUsername}";
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $bin = curl_exec($ch);
        $ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        curl_close($ch);

        if (!$bin) throw new \RuntimeException('download failed');

        $dir = \Yii::getAlias('@runtime/tg_media');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $ext = $preferExt ?: self::extFromContentType($ctype);
        if (!$ext) $ext = '.bin';
        $tmp = $dir.'/'.uniqid('tg_', true).$ext;
        file_put_contents($tmp, $bin);

        // конверт WEBP -> JPEG (Telegram иногда ругается на webp как photo)
        if (in_array($ext, ['.webp','.WEBP'], true) && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($tmp);
            if ($img) {
                $jpg = $dir.'/'.uniqid('tg_', true).'.jpg';
                imagejpeg($img, $jpg, 92);
                imagedestroy($img);
                @unlink($tmp);
                $tmp = $jpg;
            }
        }
        return $tmp;
    }

    private static function extFromContentType(string $ct): ?string
    {
        $ct = strtolower($ct);
        if (str_contains($ct, 'image/jpeg')) return '.jpg';
        if (str_contains($ct, 'image/png'))  return '.png';
        if (str_contains($ct, 'image/webp')) return '.webp';
        if (str_contains($ct, 'video/mp4'))  return '.mp4';
        if (str_contains($ct, 'application/zip')) return '.zip';
        if (str_contains($ct, 'text/plain'))  return '.txt';
        return null;
    }
    private static function guessExtFromUrlOrName(string $url, ?string $name): string
    {
        foreach ([$name, $url] as $s) {
            if (!$s) continue;
            if (preg_match('~\.(zip|rar|7z|pdf|docx?|xlsx?|pptx?|cs|txt|json|png|jpe?g|mp4|mp3|wav|webp)$~i', $s, $m)) {
                return '.'.strtolower($m[1]);
            }
        }
        return '';
    }


    private function detectType(array $post): string
    {
        if (isset($post['text'])) return 'text';
        foreach (['photo','video','animation','document','audio','voice'] as $k) {
            if (isset($post[$k])) return $k;
        }
        return 'other';
    }

    /**
     * Публикует новость в целевой канал:
     * - если есть processed_text -> sendMessage(processed_text)
     * - если тип text -> sendMessage(text)
     * - иначе copyMessage (с processed_caption или исходной caption)
     */
    public function publish(int $id): array
    {
        $news = TelegramNews::findOne($id);
        if (!$news) throw new \RuntimeException("News #{$id} not found");

        $target = $news->target_chat_id ?: (Yii::$app->settings->get('telegram_parser_target_chat_id') ?: '');
        if (!$target) throw new \RuntimeException("TARGET chat not configured");

        $meta    = json_decode($news->raw_json ?? '[]', true);
        $caption = $this->trimCaption($news->processed_caption ?: $news->processed_text ?: $news->caption);
        $referer = ltrim((string)$news->source_chat_id, '@') ?: ($meta['username'] ?? null);

        try {
            switch ($news->content_type) {
                case 'text': {
                    $text = $news->processed_text ?? $news->text ?? '';
                    $res = $this->bot->sendMessage($target, $text);
                    $news->published_message_id = (int)($res['message_id'] ?? 0);
                    $this->afterSuccess($news);
                    return ['ok'=>true,'message_id'=>$news->published_message_id];
                }

                case 'photo': {
                    if ($news->processed_caption === null && $news->processed_text === null) {
                        // если контент не меняем — можно безопасно копировать
                        $res = $this->bot->copyMessage($target, $news->source_chat_id, $news->source_message_id);
                        $news->published_message_id = (int)($res['message_id'] ?? 0);
                        $this->afterSuccess($news);
                        return ['ok'=>true,'message_id'=>$news->published_message_id];
                    }

                    $url = $meta['media'][0] ?? null;
                    if (!$url) throw new \RuntimeException('photo url missing');

//                    $tmp = $this->downloadToTemp(
//                        $url,
//                        self::guessExtFromUrlOrName($url, $meta['file_name'] ?? null),
//                        $referer ? "s/{$referer}" : null
//                    );
//                    if (!filesize($tmp)) throw new \RuntimeException('photo empty file');

                    //$file = new \CURLFile($tmp, mime_content_type($tmp) ?: 'image/jpeg', basename($tmp));
                    $res = $this->bot->sendPhoto($target, $url, $caption);
//                    @unlink($tmp);

                    $news->published_message_id = (int)($res['message_id'] ?? 0);
                    $this->afterSuccess($news);
                    return ['ok'=>true,'message_id'=>$news->published_message_id];
                }

                case 'video': {
                    if ($news->processed_caption === null && $news->processed_text === null) {
                        $res = $this->bot->copyMessage($target, $news->source_chat_id, $news->source_message_id);
                        $news->published_message_id = (int)($res['message_id'] ?? 0);
                        $this->afterSuccess($news);
                        return ['ok'=>true,'message_id'=>$news->published_message_id];
                    }

                    $url = $meta['media'][0] ?? null;
                    if (!$url) throw new \RuntimeException('video url missing');

                    $tmp = $this->downloadToTemp(
                        $url,
                        self::guessExtFromUrlOrName($url, $meta['file_name'] ?? null) ?: '.mp4',
                        $referer ? "s/{$referer}" : null
                    );
                    if (!filesize($tmp)) throw new \RuntimeException('video empty file');

                    // Telegram предпочитает mp4; webm часто не принимается как video
                    $mime = mime_content_type($tmp) ?: 'video/mp4';
                    $file = new \CURLFile($tmp, $mime, basename($tmp));
                    $res  = $this->bot->sendVideo($target, $file, $caption);
                    @unlink($tmp);

                    $news->published_message_id = (int)($res['message_id'] ?? 0);
                    $this->afterSuccess($news);
                    return ['ok'=>true,'message_id'=>$news->published_message_id];
                }

                case 'album': {
                    $urls = $meta['media'] ?? [];
                    if (!$urls) throw new \RuntimeException('album media missing');

                    $media = [];
                    $files = [];
                    $tmps  = [];

                    foreach ($urls as $idx => $u) {
                        $tmp = $this->downloadToTemp(
                            $u,
                            self::guessExtFromUrlOrName($u, null),
                            $referer ? "s/{$referer}" : null
                        );
                        if (!filesize($tmp)) { @unlink($tmp); continue; }
                        $tmps[] = $tmp;

                        $attach = 'file'.($idx+1);
                        $mime   = mime_content_type($tmp) ?: 'application/octet-stream';
                        $files[$attach] = new \CURLFile($tmp, $mime, basename($tmp));

                        $item = [
                            'type'  => $this->isVideoExtOrMime($tmp, $mime) ? 'video' : 'photo',
                            'media' => "attach://{$attach}",
                        ];
                        if ($idx === 0 && $caption) $item['caption'] = $caption;
                        $media[] = $item;
                    }

                    if (!$media) throw new \RuntimeException('album files empty');

                    $res = $this->bot->sendMediaGroup($target, $media, $files);

                    foreach ($tmps as $p) { @unlink($p); }

                    $first = is_array($res) && isset($res[0]['message_id']) ? (int)$res[0]['message_id'] : 0;
                    $news->published_message_id = $first;
                    $this->afterSuccess($news);
                    return ['ok'=>true,'message_id'=>$first,'count'=>is_countable($res)?count($res):0];
                }

                default: {
                    $res = $this->bot->copyMessage($target, $news->source_chat_id, $news->source_message_id);
                    $news->published_message_id = (int)($res['message_id'] ?? 0);
                    $this->afterSuccess($news);
                    return ['ok'=>true,'message_id'=>$news->published_message_id];
                }
            }
        } catch (\Throwable $e) {
            $news->status = TelegramNews::STATUS_FAILED;
            $news->error  = $e->getMessage();
            $news->updated_at = time();
            $news->save(false);
            return ['ok'=>false,'error'=>$e->getMessage()];
        }
    }

    private function isVideoExtOrMime(string $path, string $mime): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4','mov','m4v'], true)) return true;
        if (str_starts_with(strtolower($mime), 'video/')) return true;
        return false;
    }

    private function trimCaption(?string $caption): ?string
    {
        if ($caption === null) return null;
        // лимит подписи в Bot API для фото/видео — 1024 символа
        return mb_strlen($caption, 'UTF-8') > 1024 ? mb_substr($caption, 0, 1024, 'UTF-8') : $caption;
    }

    private function afterSuccess(TelegramNews $news): void
    {
        $news->status = TelegramNews::STATUS_PUBLISHED;
        $news->error  = null;
        $news->updated_at = time();
        $news->save(false);
    }

}
