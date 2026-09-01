<?php

namespace console\controllers;

use common\components\max\MaxBotApi;
use common\components\max\MaxSupportSettings;
use yii\console\Controller;
use yii\console\ExitCode;

class MaxController extends Controller
{
    /**
     * Регистрирует webhook MAX для ответов из чата поддержки.
     *
     * Пример: php yii max/set-webhook
     */
    public function actionSetWebhook(): int
    {
        $settings = new MaxSupportSettings();
        if (!$settings->isEnabled()) {
            $this->stdout("Интеграция MAX выключена, webhook не изменён.\n");

            return ExitCode::OK;
        }

        try {
            $result = (new MaxBotApi())->ensureSupportWebhook();
            $status = (string)($result['status'] ?? 'unknown');
            $url = (string)($result['url'] ?? $settings->supportWebhookUrl());
            $this->stdout("MAX webhook: {$status}\n  → {$url}\n");

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr('Ошибка регистрации MAX webhook: ' . $e->getMessage() . "\n");

            return ExitCode::CONFIG;
        }
    }
}
