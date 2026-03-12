<?php

namespace api\controllers\v1;

use Yii;
use yii\web\Response;
use yii\filters\VerbFilter;

/**
 * Приём данных от HighlightCaptureMod (PvP-киллы) и отправка в Telegram.
 * POST /v1/rust/highlights — тело JSON от плагина (killer_steam_id, victim_name, weapon_short_name и т.д.).
 * Опционально: заголовок X-Api-Key в конфиге плагина (проверку при необходимости добавить в конфиг API).
 */
class RustHighlightsController extends BaseApiController
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'index' => ['post'],
            ],
        ];
        return $behaviors;
    }

    /**
     * POST — принять JSON от HighlightCaptureMod и отправить в Telegram.
     * @return array
     */
    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->statusCode = 200;

        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $raw = Yii::$app->request->getRawBody();
            if (!empty($raw)) {
                $body = json_decode($raw, true) ?: [];
            }
        }

        if (empty($body)) {
            return ['ok' => false, 'error' => 'Empty body'];
        }

        $message = $this->formatHighlightMessage($body);
        try {
            Yii::$app->telegramChats->sendMessage($message);
            return ['ok' => true];
        } catch (\Throwable $e) {
            Yii::error('RustHighlights sendMessage: ' . $e->getMessage(), __METHOD__);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Форматирует сообщение для Telegram из payload плагина.
     * @param array $p
     * @return string
     */
    private function formatHighlightMessage(array $p)
    {
        $server = $p['server_id'] ?? '';
        $killer = $p['killer_name'] ?? $p['killer_steam_id'] ?? '?';
        $victim = $p['victim_name'] ?? $p['victim_steam_id'] ?? '?';
        $weapon = $p['weapon_short_name'] ?? ($p['weapon_prefab'] ?? '');
        $distance = isset($p['distance']) ? round((float) $p['distance'], 1) : null;
        $time = $p['kill_timestamp_utc'] ?? '';

        $lines = [
            '🎯 PvP kill',
            'Сервер: ' . ($server ?: '—'),
            'Убийца: ' . $killer,
            'Жертва: ' . $victim,
        ];
        if ($weapon !== '') {
            $lines[] = 'Оружие: ' . $weapon;
        }
        if ($distance !== null) {
            $lines[] = 'Дистанция: ' . $distance . ' м';
        }
        if ($time !== '') {
            $lines[] = 'Время: ' . $time;
        }

        return implode("\n", $lines);
    }
}
