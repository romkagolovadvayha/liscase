<?php

namespace console\controllers;

use common\components\telegram\TelegramCurlProxy;
use common\models\user\User;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class TelegramController extends Controller
{
    /** @var bool только показать целевые URL, без вызова setWebhook */
    public $dryRun = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['dryRun']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['d' => 'dryRun']);
    }

    private function telegramApiCall(string $botToken, string $method, array $params): array
    {
        $ch = curl_init("https://api.telegram.org/bot{$botToken}/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 15,
        ]);
        TelegramCurlProxy::applyFromSettings($ch);
        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $body = json_decode($raw, true) ?: [];

        return [
            'http' => $http,
            'ok' => $body['ok'] ?? false,
            'desc' => $body['description'] ?? null,
            'code' => $body['error_code'] ?? null,
            'params' => $body['parameters'] ?? [],
            'result' => $body['result'] ?? null,
        ];
    }

    private function isTelegramTooManyRequests(array $r): bool
    {
        if (($r['http'] ?? 0) === 429) {
            return true;
        }
        if (($r['code'] ?? 0) == 429) {
            return true;
        }
        $desc = $r['desc'] ?? '';

        return is_string($desc) && stripos($desc, 'Too Many Requests') !== false;
    }

    private function sleepForTelegramRetry(array $r): void
    {
        $sec = 1;
        $p = $r['params'] ?? [];
        if (is_array($p) && isset($p['retry_after']) && is_numeric($p['retry_after'])) {
            $sec = (int) $p['retry_after'];
        }
        $sec = max(1, min($sec, 60));
        sleep($sec);
    }

    private function call(string $method, array $params): array
    {
        $token = (string) Yii::$app->settings->get('tgbot_botToken');

        return $this->telegramApiCall($token, $method, $params);
    }

    /**
     * Зарегистрировать вебхуки ботов на URL API (params.apiPublicUrl).
     * Если tgbot_botToken и tgbotSupportAlert_token совпадают — один вызов setWebhook на …/v1/webhook/telegram/{token}.
     *
     * Пример: php yii telegram/set-webhooks
     * Проверка без запросов к Telegram: php yii telegram/set-webhooks --dryRun=1
     */
    public function actionSetWebhooks(): int
    {
        $base = (string) (Yii::$app->params['apiPublicUrl'] ?? '');
        $base = rtrim($base, '/');
        if ($base === '') {
            $this->stderr("Параметр params[apiPublicUrl] пустой. Задайте публичный URL API без завершающего слэша (common/config/params-local.php).\n");

            return ExitCode::CONFIG;
        }

        $personal = (string) Yii::$app->settings->get('tgbot_botToken');
        $support = (string) Yii::$app->settings->get('tgbotSupportAlert_token');

        $bots = [];
        if ($personal !== '' && $support !== '' && $personal === $support) {
            $bots[] = [
                'label' => 'personal + support (один токен → один webhook)',
                'token' => $personal,
                'path' => null,
            ];
        } else {
            if ($personal !== '') {
                $bots[] = [
                    'label' => 'personal (tgbot_botToken)',
                    'token' => $personal,
                    'path' => 'personal',
                ];
            }
            if ($support !== '') {
                $bots[] = [
                    'label' => 'support alert / модерация (tgbotSupportAlert_token)',
                    'token' => $support,
                    'path' => 'support',
                ];
            }
        }

        foreach ($bots as $idx => $bot) {
            if ($bot['token'] === '') {
                $this->stderr("Пропуск {$bot['label']}: пустой токен.\n");
                continue;
            }
            $hookUrl = ($bot['path'] === null || $bot['path'] === '')
                ? $base . '/v1/webhook/telegram/' . $bot['token']
                : $base . '/v1/webhook/telegram/' . $bot['path'] . '/' . $bot['token'];
            if ($this->dryRun) {
                $this->stdout("[dry-run] {$bot['label']}\n  URL: {$hookUrl}\n");
                continue;
            }

            // Несколько вызовов подряд к api.telegram.org дают 429; разносим боты.
            if ($idx > 0) {
                sleep(2);
            }

            $info = $this->telegramApiCall($bot['token'], 'getWebhookInfo', []);
            $prevUrl = is_array($info['result']) ? ($info['result']['url'] ?? '') : '';
            $this->stdout("{$bot['label']} — было: " . ($prevUrl !== '' ? $prevUrl : '(нет)') . "\n");

            $maxAttempts = 5;
            $r = ['ok' => false, 'desc' => 'unknown'];
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $r = $this->telegramApiCall($bot['token'], 'setWebhook', ['url' => $hookUrl]);
                if ($r['ok']) {
                    break;
                }
                if ($this->isTelegramTooManyRequests($r) && $attempt < $maxAttempts) {
                    $this->stderr("setWebhook 429 ({$bot['label']}), повтор через retry_after… ({$attempt}/{$maxAttempts})\n");
                    $this->sleepForTelegramRetry($r);

                    continue;
                }
                $this->stderr("setWebhook ошибка ({$bot['label']}): " . ($r['desc'] ?? 'unknown') . "\n");

                return ExitCode::UNSPECIFIED_ERROR;
            }
            if (!$r['ok']) {
                $this->stderr("setWebhook ошибка ({$bot['label']}): " . ($r['desc'] ?? 'unknown') . "\n");

                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("setWebhook OK ({$bot['label']})\n  → {$hookUrl}\n");
        }

        return ExitCode::OK;
    }

    /**
     * telegram/check
     * @return array|string[]
     */
    public function actionCheck()
    {
        $userQuery = User::find()
            ->andWhere('telegram_chat_id IS NOT NULL')
            ->andWhere(['is_telegram_blocked' => 0]);

        $blocked = 0;
        $approved = 0;
        foreach ($userQuery->batch(100) as $users) {
            /** @var User[] $users */
            foreach ($users as $user) {
                $r = $this->call('sendChatAction', ['chat_id' => $user->telegram_chat_id, 'action' => 'typing']);

                $code = $r['code'] ?? $r['http'];
                $desc = mb_strtolower($r['desc'] ?? '');

                if ($r['ok'] && $r['http'] === 200) {
                    $approved++;
                } elseif ($this->isBlocked($code, $desc)) {
                    $user->is_telegram_blocked = true;
                    $user->save(false);
                    $blocked++;
                }
            }

            echo "approved: {$approved}" . PHP_EOL;
            echo "blocked: {$blocked}" . PHP_EOL;
            echo PHP_EOL;
            sleep(10);
        }
    }

    private function isBlocked($code, $desc)
    {
        if ($code == 403 && str_contains($desc, 'bot was blocked by the user')) {
            return true;
        }
        if ($code == 403 && str_contains($desc, 'user is deactivated')) {
            return true;
        }
        if ($code == 400 && str_contains($desc, 'chat not found')) {
            return true;
        }

        return false;
    }
}
