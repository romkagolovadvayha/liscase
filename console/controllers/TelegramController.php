<?php

namespace console\controllers;

use common\models\user\User;
use yii\console\Controller;

class TelegramController extends Controller
{

    private function call(string $method, array $params): array
    {
        $token = \Yii::$app->settings->get('tgbot_botToken');
        $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $body = json_decode($raw, true) ?: [];
        return [
            'http' => $http,
            'ok'   => $body['ok'] ?? false,
            'desc' => $body['description'] ?? null,
            'code' => $body['error_code'] ?? null,
            'params' => $body['parameters'] ?? [],
        ];
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

//        $r = $this->call('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
//        if ($r['ok'] && $r['http'] === 200) return ['status' => 'ok'];
//
//        $code = $r['code'] ?? $r['http'];
//        $desc = mb_strtolower($r['desc'] ?? '');
//
//        if ($code == 403 && str_contains($desc, 'bot was blocked by the user')) return ['status' => 'blocked'];
//        if ($code == 403 && str_contains($desc, 'user is deactivated')) return ['status' => 'deactivated'];
//        if ($code == 400 && str_contains($desc, 'chat not found')) return ['status' => 'not_found'];
//        if ($code == 429) { sleep((int)($r['params']['retry_after'] ?? 1)); return $this->check($chatId); }
//        return ['status' => 'error', 'code' => $code, 'desc' => $r['desc'] ?? ''];
    }

    private function isBlocked($code, $desc) {
        if ($code == 403 && str_contains($desc, 'bot was blocked by the user')) return true;
        if ($code == 403 && str_contains($desc, 'user is deactivated')) return true;
        if ($code == 400 && str_contains($desc, 'chat not found')) return true;

        return false;
    }
}