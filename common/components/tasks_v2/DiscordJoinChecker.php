<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Проверка вступления в Discord сервер через Discord API
 */
class DiscordJoinChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = $task->check_params ?? [];
        $guildId = Yii::$app->settings->get('discord_guild_id') ?? null;

        if (!$guildId) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указан ID Discord сервера (discord_guild_id).')
            );
        }

        // Проверяем, есть ли у пользователя Discord ID
        if (empty($user->discord_id)) {
            $discordInviteLink = Yii::$app->settings->get('social_discord') ?? '';
            return CheckResult::failure(
                Yii::t('common', 'Для выполнения задания необходимо привязать Discord аккаунт. Перейдите в настройки профиля и привяжите Discord.') . 
                (!empty($discordInviteLink) ? "\n\n" . Yii::t('common', 'Ссылка на Discord сервер: {link}', ['link' => $discordInviteLink]) : '')
            );
        }

        // Получаем токен бота Discord
        $botToken = Yii::$app->settings->get('discord_bot_token');
        if (empty($botToken)) {
            Yii::error("Discord Bot Token not configured", __METHOD__);
            return CheckResult::failure(
                Yii::t('common', 'Проверка вступления в Discord временно недоступна. Попробуйте позже.')
            );
        }

        // Проверяем через Discord API, является ли пользователь участником сервера
        $isMember = $this->checkGuildMember($guildId, $user->discord_id, $botToken);

        if ($isMember) {
            return CheckResult::success(
                Yii::t('common', 'Вы успешно вступили в Discord сервер!')
            );
        }

        // Пользователь не является участником
        $discordInviteLink = Yii::$app->settings->get('social_discord') ?? '';
        $message = Yii::t('common', 'Для выполнения задания необходимо вступить в Discord сервер.') . "\n";
        if (!empty($discordInviteLink)) {
            $message .= Yii::t('common', 'Ссылка на сервер: {link}', ['link' => $discordInviteLink]) . "\n";
        }
        $message .= Yii::t('common', 'После вступления нажмите кнопку "Проверить" для завершения задания.');

        return CheckResult::failure($message);
    }

    /**
     * Проверяет, является ли пользователь участником Discord сервера
     *
     * @param string $guildId Discord Server ID (Guild ID)
     * @param string $userId Discord User ID
     * @param string $botToken Discord Bot Token
     * @return bool
     */
    private function checkGuildMember(string $guildId, string $userId, string $botToken): bool
    {
        try {
            $url = "https://discord.com/api/v10/guilds/{$guildId}/members/{$userId}";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bot ' . $botToken,
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200) {
                // Пользователь является участником сервера
                return true;
            } elseif ($httpCode === 404) {
                // Пользователь не является участником сервера
                return false;
            } else {
                // Ошибка API
                Yii::error("Discord API error: HTTP {$httpCode}, Response: {$response}, cURL Error: {$curlError}", __METHOD__);
                return false;
            }
        } catch (\Exception $e) {
            Yii::error("Discord API exception: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}











