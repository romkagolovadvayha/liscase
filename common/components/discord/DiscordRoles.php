<?php

namespace common\components\discord;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;

/**
 * Компонент для работы с ролями Discord через API
 */
class DiscordRoles extends Component
{
    /** Сколько раз повторять PUT/DELETE роли участника при 429/сети */
    private const MEMBER_ROLE_MAX_ATTEMPTS = 15;

    /**
     * PUT или DELETE роли участника с ожиданием при 429 (retry_after) и повторами при таймауте/5xx.
     *
     * @param string $method PUT|DELETE
     * @return array{httpCode: int, body: string, curlError: string}
     */
    private function requestGuildMemberRole(string $method, string $guildId, string $discordUserId, string $roleId, string $botToken): array
    {
        $url = sprintf(
            'https://discord.com/api/v10/guilds/%s/members/%s/roles/%s',
            rawurlencode($guildId),
            rawurlencode($discordUserId),
            rawurlencode($roleId)
        );

        $lastHttpCode = 0;
        $lastBody = '';
        $lastCurlError = '';

        for ($attempt = 0; $attempt < self::MEMBER_ROLE_MAX_ATTEMPTS; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bot ' . $botToken,
                'Content-Type: application/json',
            ]);

            $lastBody = (string)curl_exec($ch);
            $lastHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $lastCurlError = curl_error($ch);

            if ($lastHttpCode >= 200 && $lastHttpCode < 300) {
                return ['httpCode' => $lastHttpCode, 'body' => $lastBody, 'curlError' => $lastCurlError];
            }

            if ($lastHttpCode === 429) {
                $data = json_decode($lastBody, true);
                $wait = 1.0;
                if (is_array($data) && isset($data['retry_after'])) {
                    $wait = (float)$data['retry_after'];
                }
                $wait = min(max($wait, 0.05), 60.0);
                Yii::info(
                    "Discord 429 on {$method} member role, sleeping {$wait}s (attempt " . ($attempt + 1) . '/' . self::MEMBER_ROLE_MAX_ATTEMPTS . ')',
                    __METHOD__
                );
                usleep((int)round(($wait + 0.15) * 1000000));
                continue;
            }

            if ($lastHttpCode >= 500) {
                $delay = min(2 ** min($attempt, 4), 20);
                Yii::warning("Discord HTTP {$lastHttpCode} on {$method} member role; retry in {$delay}s", __METHOD__);
                sleep($delay);
                continue;
            }

            if ($lastHttpCode === 0 || $lastCurlError !== '') {
                $delay = min(2 ** min($attempt, 4), 20);
                Yii::warning(
                    "Discord network error on {$method} member role: HTTP {$lastHttpCode}, curl={$lastCurlError}; retry in {$delay}s",
                    __METHOD__
                );
                sleep($delay);
                continue;
            }

            return ['httpCode' => $lastHttpCode, 'body' => $lastBody, 'curlError' => $lastCurlError];
        }

        return ['httpCode' => $lastHttpCode, 'body' => $lastBody, 'curlError' => $lastCurlError !== '' ? $lastCurlError : 'max_retries_exceeded'];
    }

    /**
     * Получить ID роли по имени
     * @param string $guildId ID гильдии
     * @param string $roleName Имя роли
     * @param string $botToken Токен бота
     * @return string|null ID роли или null
     */
    public function getRoleIdByName($guildId, $roleName, $botToken)
    {
        try {
            $response = (clone Yii::$app->curl)
                ->setOption(CURLOPT_TIMEOUT, 3)
                ->setHeader('Authorization', "Bot {$botToken}")
                ->get("https://discord.com/api/v10/guilds/{$guildId}/roles");

            $roles = json_decode($response, true);
            if (is_array($roles)) {
                foreach ($roles as $role) {
                    if (isset($role['name']) && $role['name'] === $roleName) {
                        return $role['id'];
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Discord API error getting role: " . $e->getMessage(), __METHOD__);
        }

        return null;
    }

    /**
     * ID роли по имени или создание, если роли с таким именем ещё нет.
     * Перед POST делается запрос к API; после неудачного POST — ещё один GET (гонка воркеров, сеть).
     *
     * @param string $guildId ID гильдии
     * @param string $roleName Имя роли
     * @param string $botToken Токен бота
     * @return string|null ID роли или null
     */
    public function getOrCreateRoleByName($guildId, $roleName, $botToken)
    {
        $existing = $this->getRoleIdByName($guildId, $roleName, $botToken);
        if ($existing !== null) {
            return $existing;
        }

        $newId = $this->createRole($guildId, $roleName, $botToken);
        if ($newId !== null) {
            return $newId;
        }

        return $this->getRoleIdByName($guildId, $roleName, $botToken);
    }

    /**
     * Выдать роль пользователю
     * @param string $guildId ID гильдии
     * @param string $userId ID пользователя Discord
     * @param string $roleId ID роли
     * @param string $botToken Токен бота
     * @return bool Успешно ли выдана роль
     */
    public function assignRole($guildId, $userId, $roleId, $botToken)
    {
        try {
            $r = $this->requestGuildMemberRole('PUT', $guildId, $userId, $roleId, $botToken);
            return $r['httpCode'] >= 200 && $r['httpCode'] < 300;
        } catch (\Exception $e) {
            Yii::error('Discord API error assigning role: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Выдать роль подтверждения привязки Discord из настроек (discord_role_confirm).
     * Логика совпадает с прежним assignDiscordRole в AuthController: 10007 — не участник сервера.
     *
     * @param string $discordUserId Discord User ID
     * @return bool
     */
    public function assignSiteConfirmRole($discordUserId)
    {
        $guildId = Yii::$app->settings->get('discord_guild_id');
        $botToken = Yii::$app->settings->get('discord_bot_token');
        $roleId = Yii::$app->settings->get('discord_role_confirm');

        if (empty($guildId) || empty($botToken) || empty($roleId)) {
            Yii::warning('Discord role assignment: guild_id, bot_token or role_id not configured', __METHOD__);
            return false;
        }

        try {
            $r = $this->requestGuildMemberRole('PUT', $guildId, $discordUserId, $roleId, $botToken);
            $httpCode = $r['httpCode'];
            $response = $r['body'];
            $curlError = $r['curlError'];

            if ($httpCode === 204 || $httpCode === 200) {
                return true;
            }

            $errorData = json_decode($response, true);
            $errorCode = is_array($errorData) ? ($errorData['code'] ?? null) : null;

            if ($errorCode === 10007) {
                Yii::warning("Discord user {$discordUserId} is not a member of the server (code 10007). Role assignment skipped.", __METHOD__);
                return false;
            }

            Yii::error("Discord API error assigning role: HTTP {$httpCode}, Response: {$response}, cURL Error: {$curlError}", __METHOD__);
            if (method_exists(Yii::$app, 'telegramChats')) {
                Yii::$app->telegramChats->sendMessage("Discord: Failed to assign role {$roleId} to user {$discordUserId}. HTTP {$httpCode}, Response: {$response}");
            }
            return false;
        } catch (\Exception $e) {
            Yii::error('Discord role assignment exception: ' . $e->getMessage(), __METHOD__);
            if (method_exists(Yii::$app, 'telegramChats')) {
                Yii::$app->telegramChats->sendMessage('Discord: Exception assigning role: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Удалить роль у пользователя
     * @param string $guildId ID гильдии
     * @param string $userId ID пользователя Discord
     * @param string $roleId ID роли
     * @param string $botToken Токен бота
     * @return bool Успешно ли удалена роль
     */
    public function removeRole($guildId, $userId, $roleId, $botToken)
    {
        try {
            $r = $this->requestGuildMemberRole('DELETE', $guildId, $userId, $roleId, $botToken);
            return $r['httpCode'] >= 200 && $r['httpCode'] < 300;
        } catch (\Exception $e) {
            Yii::error('Discord API error removing role: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /**
     * Получить все роли пользователя
     * @param string $guildId ID гильдии
     * @param string $userId ID пользователя Discord
     * @param string $botToken Токен бота
     * @return array Массив ID ролей
     */
    public function getUserRoles($guildId, $userId, $botToken)
    {
        try {
            $response = (clone Yii::$app->curl)
                ->setOption(CURLOPT_TIMEOUT, 3)
                ->setHeader('Authorization', "Bot {$botToken}")
                ->get("https://discord.com/api/v10/guilds/{$guildId}/members/{$userId}");

            $member = json_decode($response, true);
            if (isset($member['roles']) && is_array($member['roles'])) {
                return $member['roles'];
            }
        } catch (\Exception $e) {
            Yii::error("Discord API error getting user roles: " . $e->getMessage(), __METHOD__);
        }

        return [];
    }

    /**
     * Создать роль в Discord сервере
     * @param string $guildId ID гильдии
     * @param string $roleName Имя роли
     * @param string $botToken Токен бота
     * @return string|null ID созданной роли или null
     */
    public function createRole($guildId, $roleName, $botToken)
    {
        try {
            $url = "https://discord.com/api/v10/guilds/{$guildId}/roles";
            
            // Параметры роли (можно настроить цвет, права и т.д.)
            $roleData = [
                'name' => $roleName,
                'color' => 0, // По умолчанию без цвета
                'hoist' => false, // Не выделять в списке участников
                'mentionable' => false, // Можно упоминать
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($roleData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bot ' . $botToken,
                'Content-Type: application/json',
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $role = json_decode($response, true);
                if (isset($role['id'])) {
                    return $role['id'];
                }
            } else {
                Yii::error("Discord API error creating role: HTTP {$httpCode}, Response: {$response}", __METHOD__);
            }
        } catch (\Exception $e) {
            Yii::error("Discord API error creating role: " . $e->getMessage(), __METHOD__);
        }

        return null;
    }
}

