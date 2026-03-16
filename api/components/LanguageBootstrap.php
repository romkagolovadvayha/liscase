<?php

namespace api\components;

use Yii;
use yii\base\BootstrapInterface;

/**
 * Устанавливает Yii::$app->language в начале каждого запроса по куке/заголовку/параметру.
 * Тогда все Yii::t() в API отдают перевод на выбранном языке (как в старой морде).
 * Приоритет: 1) кука NEXT_LOCALE, 2) ?language=, 3) Accept-Language, 4) ru-RU.
 */
class LanguageBootstrap implements BootstrapInterface
{
    private const LANG_MAP = [
        'en' => 'en-US',
        'ru' => 'ru-RU',
        'de' => 'de-DE',
        'uk' => 'uk-UA',
        'es' => 'es-ES',
    ];

    public function bootstrap($app)
    {
        $app->language = $this->resolveRequestLanguage();
    }

    private function resolveRequestLanguage(): string
    {
        $request = Yii::$app->request;
        // 1) Кука выбора языка (фронт ставит NEXT_LOCALE = ru | en)
        $cookie = $request->cookies->get('NEXT_LOCALE');
        if ($cookie && is_string($cookie->value)) {
            $key = strtolower(trim($cookie->value));
            if (isset(self::LANG_MAP[$key])) {
                return self::LANG_MAP[$key];
            }
            if (preg_match('/^[a-z]{2}/', $key)) {
                return self::LANG_MAP[substr($key, 0, 2)] ?? 'ru-RU';
            }
        }

        // 2) Явный параметр ?language=
        $param = $request->get('language');
        if ($param && preg_match('/^[a-z]{2}(-[A-Z]{2})?$/i', $param)) {
            $key = strtolower(strlen($param) === 2 ? $param : substr($param, 0, 2));
            return self::LANG_MAP[$key] ?? $param;
        }
        // 3) Заголовок Accept-Language
        $header = $request->headers->get('Accept-Language');
        if ($header && preg_match('/^([a-z]{2}(-[A-Z]{2})?)/i', trim(explode(',', $header)[0]), $m)) {
            $key = strtolower(substr($m[1], 0, 2));
            return self::LANG_MAP[$key] ?? $m[1];
        }

        return 'ru-RU';
    }
}
