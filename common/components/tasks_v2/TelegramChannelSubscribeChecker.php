<?php

namespace common\components\tasks_v2;

use common\components\telegram\TelegramApiHelper;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Проверка подписки на Telegram канал
 */
class TelegramChannelSubscribeChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = $task->check_params ?? [];
        $chatId = $params['chat_id'] ?? null;

        if (!$chatId) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указан ID канала Telegram.')
            );
        }

        // Проверяем, подключен ли у пользователя Telegram
        if (empty($user->telegram_chat_id)) {
            return CheckResult::failure(
                Yii::t('common', 'Telegram-бот не подключен. Перейдите в раздел настройки и подключите бота.')
            );
        }

        // Получаем токен бота из настроек
        $botToken = Yii::$app->settings->get('tgbot_botToken');
        if (empty($botToken)) {
            return CheckResult::failure(
                Yii::t('common', 'Ошибка конфигурации: токен Telegram-бота не настроен.')
            );
        }

        // Создаем экземпляр TelegramApiHelper и проверяем подписку
        $telegramApi = new TelegramApiHelper();
        $telegramApi->setToken($botToken);
        
        $response = $telegramApi->getChatMember($chatId, $user->telegram_chat_id);
        
        // Проверяем результат
        if (ArrayHelper::getValue($response, 'ok') === true) {
            $result = ArrayHelper::getValue($response, 'result', []);
            $status = ArrayHelper::getValue($result, 'status', '');
            
            // Статусы подписки: member, administrator, creator - все означают подписку
            if (in_array($status, ['member', 'administrator', 'creator'])) {
                return CheckResult::success(
                    Yii::t('common', 'Вы успешно подписались на Telegram канал!')
                );
            }
        }

        // Если подписки нет, показываем сообщение с инструкцией
        $channelUsername = $params['channel_username'] ?? null;
        if ($channelUsername) {
            $channelUrl = "https://t.me/{$channelUsername}";
        } else {
            $channelUrl = Yii::t('common', 'канал');
        }

        $message = Yii::t('common', 'Для выполнения задания:') . "\n";
        $message .= "1. " . Yii::t('common', 'Подпишитесь на Telegram канал: {url}', ['url' => $channelUrl]) . "\n";
        $message .= "2. " . Yii::t('common', 'Нажмите кнопку "Проверить" для подтверждения подписки.');

        return CheckResult::failure($message);
    }
}

