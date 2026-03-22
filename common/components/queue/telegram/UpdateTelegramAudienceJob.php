<?php

namespace common\components\queue\telegram;

use common\components\telegram\TelegramCurlProxy;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class UpdateTelegramAudienceJob extends BaseObject implements JobInterface
{
    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $userQuery = User::find()
                ->andWhere('telegram_chat_id IS NOT NULL')
                ->andWhere(['is_telegram_blocked' => 0]);

            $blocked = 0;
            $approved = 0;
            $processed = 0;

            foreach ($userQuery->batch(100) as $users) {
                /** @var User[] $users */
                foreach ($users as $user) {
                    $r = $this->_callTelegramApi('sendChatAction', ['chat_id' => $user->telegram_chat_id, 'action' => 'typing']);

                    $code = $r['code'] ?? $r['http'];
                    $desc = mb_strtolower($r['desc'] ?? '');

                    if ($r['ok'] && $r['http'] === 200) {
                        $approved++;
                    } elseif ($this->_isTelegramBlocked($code, $desc)) {
                        $user->is_telegram_blocked = true;
                        $user->save(false);
                        $blocked++;
                    }
                    $processed++;
                }

                // Задержка между батчами для соблюдения rate limits
                sleep(10);
            }

            $countTelegramUsers = User::find()
                ->andWhere('telegram_chat_id IS NOT NULL')
                ->andWhere(['is_telegram_blocked' => 0])
                ->count();

            try {
                Yii::$app->telegramChats->sendMessage(
                    "Проверка Telegram аудитории завершена!\n" .
                    "Обработано: {$processed}\n" .
                    "Заблокировано: {$blocked}\n" .
                    "Активных получателей: {$countTelegramUsers}"
                );
            } catch (\Exception $e) {
                // Игнорируем ошибки отправки в Telegram
            }
        } catch (\Exception $e) {
            try {
                Yii::$app->telegramChats->sendMessage("Ошибка при проверке Telegram аудитории: " . $e->getMessage());
            } catch (\Exception $ex) {
                // Игнорируем ошибки отправки в Telegram
            }
            Yii::error("UpdateTelegramAudienceJob error: " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Вызов Telegram API
     * @param string $method
     * @param array $params
     * @return array
     */
    private function _callTelegramApi(string $method, array $params): array
    {
        $token = Yii::$app->settings->get('tgbot_botToken');
        $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
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
            'ok'   => $body['ok'] ?? false,
            'desc' => $body['description'] ?? null,
            'code' => $body['error_code'] ?? null,
            'params' => $body['parameters'] ?? [],
        ];
    }

    /**
     * Проверка, заблокирован ли пользователь
     * @param int $code
     * @param string $desc
     * @return bool
     */
    private function _isTelegramBlocked($code, $desc): bool
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

