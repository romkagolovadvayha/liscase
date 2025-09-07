<?php
namespace common\components\telegram;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;
use common\models\telegram\TelegramNews;
use common\models\telegram\TelegramSourceCursor;

/**
 * Тянет публичные посты из t.me/s/<username>,
 * парсит DOM и создаёт записи TelegramNews (status=NEW).
 *
 * Поддерживаемые типы: text | photo | album | video | document | other
 * В raw_json сохраняются:
 *   - source    = 'public_html'
 *   - username  = <username без @>
 *   - id        = message id
 *   - type      = тип контента
 *   - datetime  = ISO-датавремя со страницы
 *   - media     = массив CDN-URL (для фото/альбомов/видео/доков, если смогли извлечь)
 *   - album_ids = id сообщений внутри альбома (для отладки/связки)
 *   - file_name = название документа (если есть)
 *   - single_url= ссылка на одиночный пост (?single), если пригодится при последующей обработке
 */
class TelegramPublicPullService extends Component
{
    /** @var TelegramNewsCallbackInterface|null */
    public $callback;

    /** @var string */
    public $userAgent = 'Mozilla/5.0 (compatible; ProstojTelegramPuller/1.1)';

    public function __construct($config = [])
    {
        parent::__construct($config);
        // DI: если интерфейс зарегистрирован в контейнере — возьмём его
        if ($this->callback === null && Yii::$container->has(TelegramNewsCallbackInterface::class)) {
            $this->callback = Yii::$container->get(TelegramNewsCallbackInterface::class);
        }
    }

    /**
     * Спарсить t.me/s/<username>, найти новые посты и создать TelegramNews (status=NEW).
     *
     * @param string $username @name или name
     * @param int    $limit    максимум новых записей за проход (по возрастанию id)
     * @return int   сколько новых записей добавлено
     */
    public function pullUsername(string $username, int $limit = 20): int
    {
        $username = ltrim(trim($username), '@');
        if ($username === '') return 0;

        $html = $this->fetch("https://t.me/s/{$username}");
        if (!$html) return 0;

        $parsed = $this->parsePostsDom($html, $username); // список: id, text, type, media[], datetime, album_ids, file_name, single_url
        if (!$parsed) return 0;

        // Курсор по источнику
        $cursor = TelegramSourceCursor::findOne(['source' => $username]);
        if (!$cursor) {
            $cursor = new TelegramSourceCursor();
            $cursor->source = $username;
            $cursor->last_message_id = 0;
            $cursor->updated_at = time();
            $cursor->save(false);
        }

        // Только id > курсора
        $newPosts = array_values(array_filter($parsed, fn($p) => ($p['id'] ?? 0) > $cursor->last_message_id));
        if (!$newPosts) return 0;

        // По возрастанию и лимит
        usort($newPosts, fn($a, $b) => ($a['id'] <=> $b['id']));
        if ($limit > 0) $newPosts = array_slice($newPosts, 0, $limit);

        $created = 0;

        foreach ($newPosts as $p) {
            $sourceChat = '@' . $username;
            $mid = (int)$p['id'];

            // Идемпотентность
            $exists = TelegramNews::find()
                                  ->where(['source_chat_id' => $sourceChat, 'source_message_id' => $mid])
                                  ->exists();

            if ($exists) {
                if ($mid > $cursor->last_message_id) {
                    $cursor->last_message_id = $mid;
                    $cursor->updated_at = time();
                    $cursor->save(false);
                }
                continue;
            }

            $news = new TelegramNews();
            $news->source_chat_id     = $sourceChat;
            $news->source_message_id  = $mid;
            $news->media_group_id     = ($p['type'] === 'album') ? ($username.'/'.$mid) : null;
            $news->content_type       = $p['type']; // text|photo|album|video|document|other
            $news->processed_text     = $p['text'] ?? null; // подсказка модерации
            $news->target_chat_id     = getenv('telegram_parser_target_chat_id') ?: null;
            $news->status             = TelegramNews::STATUS_NEW;

            // Сохраняем максимум полезного в raw_json
            $raw = [
                'source'     => 'public_html',
                'username'   => $username,
                'id'         => $mid,
                'type'       => $p['type'],
                'datetime'   => $p['datetime'] ?? null,
                'media'      => array_values(array_unique($p['media'] ?? [])),
                'album_ids'  => array_values(array_unique($p['album_ids'] ?? [])),
            ];
            if (!empty($p['file_name']))  $raw['file_name']  = $p['file_name'];
            if (!empty($p['single_url'])) $raw['single_url'] = $p['single_url'];

            $news->raw_json   = json_encode($raw, JSON_UNESCAPED_UNICODE);
            $news->created_at = $news->updated_at = time();
            $news->save(false);

            // колбэк пользователя сервиса
            if ($this->callback instanceof TelegramNewsCallbackInterface) {
                $this->callback->onNewTelegramNews($news);
            }

            $created++;

            // Сдвинем курсор
            if ($mid > $cursor->last_message_id) {
                $cursor->last_message_id = $mid;
                $cursor->updated_at = time();
                $cursor->save(false);
            }
        }

        return $created;
    }

    /**
     * GET с заголовком User-Agent. Возвращает HTML или null.
     */
    private function fetch(string $url): ?string
    {
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        try {
            $resp = $client->createRequest()
                           ->setMethod('GET')
                           ->setUrl($url)
                           ->addHeaders(['User-Agent' => $this->userAgent])
                           ->send();
        } catch (\Throwable $e) {
            Yii::warning("Fetch exception for $url: ".$e->getMessage(), __METHOD__);
            return null;
        }

        if (!$resp->isOk) {
            Yii::warning("Fetch failed {$resp->statusCode} for $url", __METHOD__);
            return null;
        }
        return $resp->getContent();
    }

    /**
     * Разбираем DOM: возвращаем список постов:
     * [
     *   [
     *     'id'        => 123,
     *     'text'      => '...',
     *     'type'      => 'text|photo|album|video|document|other',
     *     'media'     => ['cdn urls'...],
     *     'datetime'  => '2025-09-07T00:00:01+00:00',
     *     'album_ids' => [ids...],
     *     'file_name' => 'CopyPaste.cs',          // (док)
     *     'single_url'=> 'https://t.me/name/17?single' // (док, на всякий случай)
     *   ],
     *   ...
     * ]
     */
    private function parsePostsDom(string $html, string $username): array
    {
        $username = ltrim($username, '@');

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xp = new \DOMXPath($doc);

        $nodes = $xp->query("//div[contains(@class,'tgme_widget_message') and @data-post]");
        if (!$nodes || $nodes->length === 0) return [];

        $out = [];

        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $dataPost = $node->getAttribute('data-post'); // username/ID
            if (!preg_match('~^'.preg_quote($username,'~').'/(\d+)$~u', $dataPost, $m)) {
                continue;
            }
            $baseId = (int)$m[1];

            // Текст
            $text = null;
            $tn = $xp->query(".//*[contains(@class,'js-message_text')]", $node)->item(0);
            if ($tn instanceof \DOMElement) {
                $t = trim($tn->textContent);
                $text = ($t !== '') ? $t : null;
            }

            // Время
            $dt = null;
            $time = $xp->query(".//a[contains(@class,'tgme_widget_message_date')]/time", $node)->item(0);
            if ($time instanceof \DOMElement) {
                $dt = $time->getAttribute('datetime') ?: null;
            }

            // Начальные значения
            $type = 'text';
            $mediaUrls = [];
            $albumIds  = [];
            $fileName  = null;
            $singleUrl = null;

            // --- 1) Альбом: миниатюры внутри группы ---
            $photoAnchors = $xp->query(".//a[contains(@class,'js-message_photo')]", $node);
            if ($photoAnchors && $photoAnchors->length > 0) {
                $type = ($photoAnchors->length > 1) ? 'album' : 'photo';
                foreach ($photoAnchors as $a) {
                    /** @var \DOMElement $a */
                    $style = $a->getAttribute('style');
                    if ($style && preg_match("~background-image:url\\('([^']+)'\\)~", $style, $mm)) {
                        $mediaUrls[] = html_entity_decode($mm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                    $href = $a->getAttribute('href'); // https://t.me/username/13?single
                    if ($href && preg_match('~/'.preg_quote($username,'~').'/(\d+)~', $href, $mid)) {
                        $albumIds[] = (int)$mid[1];
                    }
                }
            }

            // --- 2) Одиночное фото (если не нашли альбом) ---
            if ($type === 'text') {
                $singlePhoto = $xp->query(".//a[contains(@class,'tgme_widget_message_photo_wrap')]", $node);
                if ($singlePhoto && $singlePhoto->length > 0) {
                    $type = 'photo';
                    /** @var \DOMElement $a */
                    $a = $singlePhoto->item(0);
                    $style = $a->getAttribute('style');
                    if ($style && preg_match("~background-image:url\\('([^']+)'\\)~", $style, $mm)) {
                        $mediaUrls[] = html_entity_decode($mm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
            }

            // --- 3) Видео (если не фото) ---
            if ($type === 'text') {
                $vA = $xp->query(".//a[contains(@class,'tgme_widget_message_video_player')]", $node)->item(0);
                if ($vA instanceof \DOMElement) {
                    $type = 'video';
                    $video = $xp->query(".//video", $vA)->item(0);
                    if ($video instanceof \DOMElement) {
                        $src = $video->getAttribute('src');
                        if ($src) {
                            $mediaUrls[] = html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    }
                }
            }

            // --- 4) Документ (если не фото/видео) ---
            if ($type === 'text') {
                $docA = $xp->query(".//a[contains(@class,'tgme_widget_message_document_wrap')]", $node);
                if ($docA && $docA->length > 0) {
                    $type = 'document';

                    // Название файла (если есть)
                    $titleNode = $xp->query(".//div[contains(@class,'tgme_widget_message_document_title')]", $node)->item(0);
                    if ($titleNode instanceof \DOMElement) {
                        $fn = trim($titleNode->textContent);
                        if ($fn !== '') $fileName = $fn;
                    }

                    // Попробуем вытащить прямой CDN из одиночной страницы поста
                    $singleUrl = "https://t.me/{$username}/{$baseId}?single";
                    $singleHtml = $this->fetch($singleUrl);
                    if ($singleHtml) {
                        // Ищем любой cdnX.telesco.pe/file/...
                        if (preg_match('~https://cdn\d+\.telesco\.pe/file/[^\s"\'<>]+~i', $singleHtml, $mm)) {
                            $mediaUrls[] = html_entity_decode($mm[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    }
                }
            }

            // Если вообще ничего не нашли, отметим "other"
            if ($type === 'text' && $text === null) {
                $type = 'other';
            }

            $out[] = [
                'id'         => $baseId,
                'type'       => $type,
                'text'       => $text,
                'datetime'   => $dt,
                'media'      => array_values(array_unique($mediaUrls)),
                'album_ids'  => array_values(array_unique($albumIds)),
                'file_name'  => $fileName,
                'single_url' => $singleUrl,
            ];
        }

        return $out;
    }
}
