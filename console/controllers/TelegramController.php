<?php

namespace console\controllers;

use common\components\telegram\foreignSystem\RustotekaBotSystem;
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

    private function call(string $method, array $params): array
    {
        $token = (string) Yii::$app->settings->get('tgbot_botToken');

        return $this->telegramApiCall($token, $method, $params);
    }

    /**
     * Зарегистрировать вебхуки персонального бота и Rustoteka-бота на URL API (params.apiPublicUrl).
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

        $bots = [
            [
                'label' => 'personal (tgbot_botToken)',
                'token' => (string) Yii::$app->settings->get('tgbot_botToken'),
                'path' => 'personal',
            ],
            [
                'label' => 'rustoteka',
                'token' => (new RustotekaBotSystem())->getTelegramToken(),
                'path' => 'rustoteka',
            ],
        ];

        foreach ($bots as $bot) {
            if ($bot['token'] === '') {
                $this->stderr("Пропуск {$bot['label']}: пустой токен.\n");
                continue;
            }
            $hookUrl = $base . '/v1/webhook/telegram/' . $bot['path'] . '/' . rawurlencode($bot['token']);
            if ($this->dryRun) {
                $this->stdout("[dry-run] {$bot['label']}\n  URL: {$hookUrl}\n");
                continue;
            }

            $info = $this->telegramApiCall($bot['token'], 'getWebhookInfo', []);
            $prevUrl = is_array($info['result']) ? ($info['result']['url'] ?? '') : '';
            $this->stdout("{$bot['label']} — было: " . ($prevUrl !== '' ? $prevUrl : '(нет)') . "\n");

            $r = $this->telegramApiCall($bot['token'], 'setWebhook', ['url' => $hookUrl]);
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
