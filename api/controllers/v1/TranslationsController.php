<?php

namespace api\controllers\v1;

use Yii;
use yii\db\Query;

/**
 * Переводы для фронта (аналог Yii::t / DbMessageSource).
 * GET /v1/translations — возвращает все переводы для языка одним запросом.
 * GET /v1/translations/new-front — переводы категории "new_front": слева оригинал, справа перевод на выбранный язык.
 * Язык задаётся в api\components\LanguageBootstrap (кука NEXT_LOCALE / ?language= / Accept-Language).
 * Параметр ?keys= или POST {"keys": [...]} — недостающие ключи автоматически добавляются в БД (category=frontend).
 */
class TranslationsController extends BaseApiController
{
    private const CATEGORY_FRONTEND = 'frontend';
    private const CATEGORY_NEW_FRONT = 'new_front';
    private const LANGUAGES_ENSURE = ['ru-RU', 'en-US'];

    /**
     * Список всех переводов для запрошенного языка.
     * Ключ = исходный текст (например русский); перевод приходит из БД.
     * Ответ: { "key": "translation", ... } — ключ как в language_source.message.
     * Ключи: GET ?keys= (для коротких без запятых) или POST body JSON {"keys": ["текст1", "текст2"]}.
     */
    public function actionIndex()
    {
        $language = Yii::$app->language;
        $keys = $this->getRequestKeys();
        if (!empty($keys)) {
            $this->ensureKeys($keys);
        }

        $rows = (new Query())
            ->select(['ls.message', 'COALESCE(lt.translation, ls.message) as translation'])
            ->from(['ls' => '{{%language_source}}'])
            ->leftJoin(['lt' => '{{%language_translate}}'], 'ls.id = lt.id AND lt.language = :lang')
            ->where(['ls.category' => self::CATEGORY_FRONTEND])
            ->addParams([':lang' => $language])
            ->all(Yii::$app->db);

        $data = [];
        foreach ($rows as $row) {
            $data[$row['message']] = (string) ($row['translation'] ?? $row['message']);
        }

        return $this->successResponse($data);
    }

    /**
     * Переводы категории "new_front" для выбранного языка.
     * Ответ: { "оригинал": "перевод", ... } — ключ = исходный текст (язык источника), значение = перевод на Yii::$app->language.
     */
    public function actionNewFront()
    {
        $language = Yii::$app->language;

        $rows = (new Query())
            ->select(['ls.message', 'COALESCE(lt.translation, ls.message) as translation'])
            ->from(['ls' => '{{%language_source}}'])
            ->leftJoin(['lt' => '{{%language_translate}}'], 'ls.id = lt.id AND lt.language = :lang')
            ->where(['ls.category' => self::CATEGORY_NEW_FRONT])
            ->addParams([':lang' => $language])
            ->all(Yii::$app->db);

        $data = [];
        foreach ($rows as $row) {
            $data[$row['message']] = (string) ($row['translation'] ?? $row['message']);
        }

        return $this->successResponse($data);
    }

    /**
     * Сообщить об отсутствующем переводе (new_front): добавить ключ в БД и отправить в Telegram.
     * POST body: {"key": "оригинальный текст"}. Язык берётся из Accept-Language / Yii::$app->language.
     * Фронт вызывает при t('текст'), когда перевода нет; повтор для того же ключа блокируется на 5 мин на клиенте.
     */
    public function actionReportMissing()
    {
        $request = Yii::$app->request;
        if (!$request->isPost) {
            return $this->errorResponse('METHOD_NOT_ALLOWED', 'Method not allowed', [], 405);
        }
        if ($request->contentType !== 'application/json') {
            return $this->errorResponse('BAD_REQUEST', 'Content-Type must be application/json', [], 400);
        }
        $body = $request->getRawBody();
        if ($body === null || $body === '') {
            return $this->errorResponse('BAD_REQUEST', 'Body required', [], 400);
        }
        $decoded = json_decode($body, true);
        $key = isset($decoded['key']) && is_string($decoded['key']) ? trim($decoded['key']) : '';
        if ($key === '' || mb_strlen($key) > 2000) {
            return $this->errorResponse('BAD_REQUEST', 'Invalid key', [], 400);
        }

        $language = Yii::$app->language;
        $this->ensureNewFrontKey($key);

        $message = "🌐 Перевод new_front\n"
            . "Переводим: " . $key . "\n"
            . "На язык: " . $language . "\n"
            . "(перевод в БД отсутствует — добавьте в language_translate)";
        try {
            if (isset(Yii::$app->telegramChats)) {
                Yii::$app->telegramChats->sendMessage($message);
            }
        } catch (\Throwable $e) {
            Yii::warning('telegramChats sendMessage failed: ' . $e->getMessage(), __METHOD__);
        }

        return $this->successResponse(['reported' => true]);
    }

    /**
     * Добавить ключ в language_source (category new_front) и пустые записи в language_translate, если ещё нет.
     */
    private function ensureNewFrontKey(string $message): void
    {
        $existing = (new Query())
            ->from('{{%language_source}}')
            ->where(['category' => self::CATEGORY_NEW_FRONT, 'message' => $message])
            ->select('id')
            ->scalar(Yii::$app->db);
        if ($existing !== false) {
            return;
        }
        try {
            Yii::$app->db->createCommand()->insert('{{%language_source}}', [
                'category' => self::CATEGORY_NEW_FRONT,
                'message' => $message,
            ])->execute();
            $id = (int) Yii::$app->db->getLastInsertID();
            if ($id > 0) {
                foreach (self::LANGUAGES_ENSURE as $lang) {
                    Yii::$app->db->createCommand()->insert('{{%language_translate}}', [
                        'id' => $id,
                        'language' => $lang,
                        'translation' => $message,
                    ])->execute();
                }
            }
        } catch (\Throwable $e) {
            // Дубликат или другая ошибка — игнорируем
        }
    }

    /**
     * @param string[] $keys
     */
    private function ensureKeys(array $keys): void
    {
        if (empty($keys)) {
            return;
        }
        $keys = array_unique(array_map('trim', $keys));
        $keys = array_filter($keys, static function ($k) {
            return $k !== '' && mb_strlen($k) <= 2000;
        });
        if (empty($keys)) {
            return;
        }

        $existing = (new Query())
            ->from('{{%language_source}}')
            ->where(['category' => self::CATEGORY_FRONTEND, 'message' => $keys])
            ->select('message')
            ->column(Yii::$app->db);
        $existing = array_flip($existing);
        $toInsert = array_diff_key(array_flip($keys), $existing);
        if (empty($toInsert)) {
            return;
        }

        $db = Yii::$app->db;
        foreach (array_keys($toInsert) as $message) {
            try {
                $db->createCommand()->insert('{{%language_source}}', [
                    'category' => self::CATEGORY_FRONTEND,
                    'message' => $message,
                ])->execute();
                $id = (int) $db->getLastInsertID();
                if ($id > 0) {
                    foreach (self::LANGUAGES_ENSURE as $lang) {
                        $db->createCommand()->insert('{{%language_translate}}', [
                            'id' => $id,
                            'language' => $lang,
                            'translation' => $message,
                        ])->execute();
                    }
                }
            } catch (\Throwable $e) {
                // Игнорируем дубликаты и прочие ошибки при вставке
            }
        }
    }

    /**
     * Ключи: POST body JSON {"keys": [...]} или GET ?keys= (разделитель запятая).
     * @return string[]
     */
    private function getRequestKeys(): array
    {
        $request = Yii::$app->request;
        if ($request->isPost && $request->contentType === 'application/json') {
            $body = $request->getRawBody();
            if ($body !== null && $body !== '') {
                $decoded = json_decode($body, true);
                if (isset($decoded['keys']) && is_array($decoded['keys'])) {
                    return array_values($decoded['keys']);
                }
            }
        }
        $keysParam = $request->get('keys');
        if ($keysParam !== null && $keysParam !== '') {
            return $this->parseKeysParam($keysParam);
        }
        return [];
    }

    /**
     * @return string[]
     */
    private function parseKeysParam(string $keysParam): array
    {
        return array_filter(explode(',', $keysParam));
    }

}
