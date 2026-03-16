<?php

namespace api\controllers\v1;

use Yii;
use yii\db\Query;

/**
 * Переводы для фронта (аналог Yii::t / DbMessageSource).
 * GET /v1/translations — возвращает все переводы для языка одним запросом.
 * Язык (приоритет): 1) кука NEXT_LOCALE (ru/en), 2) ?language=, 3) заголовок Accept-Language, 4) по умолчанию ru-RU.
 * Параметр ?keys= или POST {"keys": [...]} — недостающие ключи автоматически добавляются в БД (category=frontend).
 */
class TranslationsController extends BaseApiController
{
    private const CATEGORY_FRONTEND = 'frontend';
    private const LANGUAGES_ENSURE = ['ru-RU', 'en-US'];

    /**
     * Список всех переводов для запрошенного языка.
     * Ключ = исходный текст (например русский); перевод приходит из БД.
     * Ответ: { "key": "translation", ... } — ключ как в language_source.message.
     * Ключи: GET ?keys= (для коротких без запятых) или POST body JSON {"keys": ["текст1", "текст2"]}.
     */
    public function actionIndex()
    {
        $language = $this->getRequestLanguage();
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

    private function getRequestLanguage(): string
    {
        $map = ['en' => 'en-US', 'ru' => 'ru-RU', 'de' => 'de-DE', 'uk' => 'uk-UA', 'es' => 'es-ES'];

        // 1) Кука выбора языка (фронт ставит NEXT_LOCALE = ru | en)
        $cookie = Yii::$app->request->cookies->get('NEXT_LOCALE');
        if ($cookie && is_string($cookie->value)) {
            $key = strtolower(trim($cookie->value));
            if (isset($map[$key])) {
                return $map[$key];
            }
            if (preg_match('/^[a-z]{2}/', $key)) {
                return $map[substr($key, 0, 2)] ?? 'ru-RU';
            }
        }

        // 2) Явный параметр ?language=
        $param = Yii::$app->request->get('language');
        if ($param && preg_match('/^[a-z]{2}(-[A-Z]{2})?$/i', $param)) {
            $key = strtolower(strlen($param) === 2 ? $param : substr($param, 0, 2));
            return $map[$key] ?? $param;
        }

        // 3) Заголовок Accept-Language
        $header = Yii::$app->request->headers->get('Accept-Language');
        if ($header && preg_match('/^([a-z]{2}(-[A-Z]{2})?)/i', trim(explode(',', $header)[0]), $m)) {
            $key = strtolower(substr($m[1], 0, 2));
            return $map[$key] ?? $m[1];
        }

        return 'ru-RU';
    }
}
