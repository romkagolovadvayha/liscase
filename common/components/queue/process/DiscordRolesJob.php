<?php

namespace common\components\queue\process;

use common\components\discord\DiscordRoles;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\support\Support;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Job для автоматической проверки и выдачи ролей Discord на основе статистики
 */
class DiscordRolesJob extends BaseObject implements JobInterface
{
    // Роли по часам игры (playtime) - название содержит критерий получения
    const ROLES_PLAYTIME = [
        ['name' => 'Новичок (<100ч игры)', 'min' => 0, 'max' => 100],
        ['name' => 'Опытный (100-500ч игры)', 'min' => 100, 'max' => 500],
        ['name' => 'Ветеран (500-1000ч игры)', 'min' => 500, 'max' => 1000],
        ['name' => 'Легенда (1000-5000ч игры)', 'min' => 1000, 'max' => 5000],
        ['name' => 'Мастер (5000-10000ч игры)', 'min' => 5000, 'max' => 10000],
        ['name' => 'Бессмертный (10000+ч игры)', 'min' => 10000, 'max' => PHP_INT_MAX],
    ];

    // Роли по киллам - название содержит критерий получения
    const ROLES_KILLS = [
        ['name' => 'Стрелок (<500 киллов)', 'min' => 0, 'max' => 500],
        ['name' => 'Охотник (500-1000 киллов)', 'min' => 500, 'max' => 1000],
        ['name' => 'Хищник (1000-3000 киллов)', 'min' => 1000, 'max' => 3000],
        ['name' => 'Гроза (3000-5000 киллов)', 'min' => 3000, 'max' => 5000],
        ['name' => 'Маньяк (5000-10000 киллов)', 'min' => 5000, 'max' => 10000],
        ['name' => 'Уничтожитель (10000+ киллов)', 'min' => 10000, 'max' => PHP_INT_MAX],
    ];

    // Роли по вайпам - название содержит критерий получения
    const ROLES_WIPES = [
        ['name' => 'Выживший (1-10 вайпов)', 'min' => 1, 'max' => 10],
        ['name' => 'Старожил (10-25 вайпов)', 'min' => 10, 'max' => 25],
        ['name' => 'Ветеран (25-50 вайпов)', 'min' => 25, 'max' => 50],
        ['name' => 'Железный (50-100 вайпов)', 'min' => 50, 'max' => 100],
        ['name' => 'Разрушитель (100-200 вайпов)', 'min' => 100, 'max' => 200],
        ['name' => 'Архивариус (200+ вайпов)', 'min' => 200, 'max' => PHP_INT_MAX],
    ];

    // Роли по поддержке - название содержит критерий получения (ироничный подход)
    const ROLES_SUPPORT = [
        ['name' => 'Любопытный (3-10 тикетов)', 'min' => 3, 'max' => 10],
        ['name' => 'Вопрошающий (10-50 тикетов)', 'min' => 10, 'max' => 50],
        ['name' => 'Незнайка (50-100 тикетов)', 'min' => 50, 'max' => 100],
        ['name' => 'Вечный вопрос (100+ тикетов)', 'min' => 100, 'max' => PHP_INT_MAX],
    ];

    // Роли по репортам - название содержит критерий получения
    const ROLES_REPORTS = [
        ['name' => 'Информатор (1-100 репортов)', 'min' => 1, 'max' => 100],
        ['name' => 'Сыщик (100-500 репортов)', 'min' => 100, 'max' => 500],
        ['name' => 'Жалоба (500+ репортов)', 'min' => 500, 'max' => PHP_INT_MAX],
    ];

    // Специальные роли (не зависят от статистики)
    const ROLE_VIP = 'VIP';

    /**
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        try {
            $guildId = Yii::$app->settings->get('discord_guild_id');
            $botToken = Yii::$app->settings->get('discord_bot_token');

            if (empty($guildId) || empty($botToken)) {
                Yii::error('Discord guild ID or bot token not configured', __METHOD__);
                return;
            }

            $discordRoles = new DiscordRoles();

            // Создаем роли в Discord, если их нет
            $this->ensureRolesExist($guildId, $botToken, $discordRoles);

            // Получаем всех пользователей с discord_id
            $users = User::find()
                ->andWhere(['IS NOT', 'discord_id', null])
                ->andWhere(['<>', 'discord_id', ''])
                ->all();

            Yii::info("Processing " . count($users) . " users for Discord roles", __METHOD__);

            foreach ($users as $user) {
                try {
                    $this->processUserRoles($user, $guildId, $botToken, $discordRoles);
                } catch (\Exception $e) {
                    Yii::error("Error processing user {$user->id}: " . $e->getMessage(), __METHOD__);
                }
            }

            Yii::info("Discord roles check completed", __METHOD__);
        } catch (\Exception $ex) {
            Yii::error("DiscordRolesJob error: " . $ex->getFile() . ":" . $ex->getLine() . " - " . $ex->getMessage(), __METHOD__);
            if (method_exists(Yii::$app, 'telegramChats')) {
                Yii::$app->telegramChats->sendMessage('DiscordRolesJob: ' . PHP_EOL . $ex->getFile() . ": " . $ex->getLine() . PHP_EOL . $ex->getMessage());
            }
        }
    }

    /**
     * Кэш всех ролей гильдии [roleId => roleName]
     * @var array
     */
    protected static $guildRolesCache = [];

    /**
     * Убедиться, что все необходимые роли существуют в Discord
     * @param string $guildId
     * @param string $botToken
     * @param DiscordRoles $discordRoles
     */
    public function ensureRolesExist($guildId, $botToken, $discordRoles)
    {
        // Загружаем существующие роли
        $this->loadGuildRoles($guildId, $botToken);
        
        $allRoles = [];
        
        // Собираем все роли из всех категорий
        foreach (self::ROLES_PLAYTIME as $role) {
            $allRoles[] = $role['name'];
        }
        foreach (self::ROLES_KILLS as $role) {
            $allRoles[] = $role['name'];
        }
        $allRoles[] = 'Чемпион (TOP-1 киллов)'; // TOP-1 киллов за вайп
        foreach (self::ROLES_WIPES as $role) {
            $allRoles[] = $role['name'];
        }
        foreach (self::ROLES_SUPPORT as $role) {
            $allRoles[] = $role['name'];
        }
        foreach (self::ROLES_REPORTS as $role) {
            $allRoles[] = $role['name'];
        }
        
        // Специальные роли
        $allRoles[] = self::ROLE_VIP;
        
        // Роли по серверам (создаем для всех активных серверов, чтобы они существовали в Discord)
        $servers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->andWhere(['IS NOT', 'monitoring_name', null])
            ->andWhere(['<>', 'monitoring_name', ''])
            ->all();
        
        foreach ($servers as $server) {
            $wipeTypeSuffix = $this->getWipeTypeSuffix($server->wipe_type);
            $allRoles[] = 'Сервер: ' . $server->monitoring_name . $wipeTypeSuffix;
        }
        
        // Убираем дубликаты
        $allRoles = array_unique($allRoles);
        
        // Проверяем и создаем отсутствующие роли
        $existingRoleNames = [];
        if (isset(self::$guildRolesCache[$guildId])) {
            $existingRoleNames = array_values(self::$guildRolesCache[$guildId]);
        }
        
        foreach ($allRoles as $roleName) {
            if (!in_array($roleName, $existingRoleNames)) {
                $roleId = $discordRoles->createRole($guildId, $roleName, $botToken);
                if ($roleId) {
                    Yii::info("Created Discord role: {$roleName} (ID: {$roleId})", __METHOD__);
                    // Обновляем кэш
                    self::$guildRolesCache[$guildId][$roleId] = $roleName;
                } else {
                    Yii::warning("Failed to create Discord role: {$roleName}", __METHOD__);
                }
            }
        }
    }

    /**
     * Обработать роли для пользователя
     * @param User $user
     * @param string $guildId
     * @param string $botToken
     * @param DiscordRoles $discordRoles
     */
    public function processUserRoles($user, $guildId, $botToken, $discordRoles)
    {
        // Загружаем кэш ролей гильдии если еще не загружен
        if (!isset(self::$guildRolesCache[$guildId])) {
            $this->loadGuildRoles($guildId, $botToken);
        }

        // Получаем текущие роли пользователя
        $currentRoleIds = $discordRoles->getUserRoles($guildId, $user->discord_id, $botToken);
        $currentRoleNames = [];
        
        // Получаем имена текущих ролей из кэша
        foreach ($currentRoleIds as $roleId) {
            if (isset(self::$guildRolesCache[$guildId][$roleId])) {
                $currentRoleNames[] = self::$guildRolesCache[$guildId][$roleId];
            }
        }

        // Определяем какие роли должны быть у пользователя
        $shouldHaveRoles = [];

        // Роли по часам игры
        $playtime = $this->getUserPlaytime($user->steam_id);
        $playtimeRole = $this->getRoleForValue($playtime, self::ROLES_PLAYTIME);
        if ($playtimeRole) {
            $shouldHaveRoles[] = $playtimeRole['name'];
        }

        // Роли по киллам
        $kills = $this->getUserKills($user->steam_id);
        $killsRole = $this->getRoleForValue($kills, self::ROLES_KILLS);
        if ($killsRole) {
            $shouldHaveRoles[] = $killsRole['name'];
        }
        
        // Проверяем TOP-1 киллов за вайп (уникальная роль - Чемпион)
        $topKillsRole = $this->getTopKillsRole($user->steam_id);
        if ($topKillsRole) {
            $shouldHaveRoles[] = $topKillsRole;
        }

        // Роли по вайпам
        $wipes = $this->getUserWipes($user->steam_id);
        $wipesRole = $this->getRoleForValue($wipes, self::ROLES_WIPES);
        if ($wipesRole) {
            $shouldHaveRoles[] = $wipesRole['name'];
        }

        // Роли по поддержке (до 3 ролей)
        $supportCount = $this->getUserSupportCount($user->id);
        $supportRole = $this->getRoleForValue($supportCount, self::ROLES_SUPPORT);
        if ($supportRole) {
            $shouldHaveRoles[] = $supportRole['name'];
        }

        // Роли по репортам (до 3 ролей)
        $reportsCount = $this->getUserReportsCount($user->steam_id);
        $reportsRole = $this->getRoleForValue($reportsCount, self::ROLES_REPORTS);
        if ($reportsRole) {
            $shouldHaveRoles[] = $reportsRole['name'];
        }

        // Роль VIP (если у пользователя есть активный VIP на сайте)
        if ($user->hasVip()) {
            $shouldHaveRoles[] = self::ROLE_VIP;
        }

        // Роль текущего сервера (на котором сейчас играет пользователь)
        $currentServerRole = $this->getUserCurrentServerRole($user);
        if ($currentServerRole) {
            $shouldHaveRoles[] = $currentServerRole;
        }

        // Удаляем старые роли категории и выдаем новые
        $this->updateUserRoles($user, $guildId, $botToken, $discordRoles, $currentRoleNames, $shouldHaveRoles);
    }

    /**
     * Получить роль для значения
     * @param int $value
     * @param array $roles
     * @return array|null
     */
    protected function getRoleForValue($value, $roles)
    {
        foreach ($roles as $role) {
            if ($value >= $role['min'] && $value < $role['max']) {
                return $role;
            }
        }
        return null;
    }

    /**
     * Получить общее время игры пользователя
     * @param string $steamId
     * @return int Часы игры
     */
    protected function getUserPlaytime($steamId)
    {
        $totalSeconds = Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'playtime'])
            ->sum('value') ?: 0;

        return (int)($totalSeconds / 60); // Конвертируем секунды в часы
    }

    /**
     * Получить общее количество киллов пользователя
     * @param string $steamId
     * @return int
     */
    protected function getUserKills($steamId)
    {
        return (int)Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'kills'])
            ->sum('value') ?: 0;
    }

    /**
     * Получить количество уникальных вайпов пользователя
     * @param string $steamId
     * @return int
     */
    protected function getUserWipes($steamId)
    {
        return (int)Statistics::find()
            ->select('wipe')
            ->andWhere(['steam_id' => $steamId])
            ->distinct()
            ->count();
    }

    /**
     * Получить роль TOP-1 киллов за вайп
     * @param string $steamId
     * @return string|null
     */
    protected function getTopKillsRole($steamId)
    {
        // Получаем текущий вайп (последний активный)
        $currentWipe = Statistics::find()
            ->select('wipe')
            ->orderBy(['wipe' => SORT_DESC])
            ->limit(1)
            ->scalar();

        if (empty($currentWipe)) {
            return null;
        }

        // Получаем топ игроков по киллам за текущий вайп
        $topKills = Statistics::find()
            ->select([
                'steam_id',
                'SUM(value) as total_kills'
            ])
            ->andWhere(['wipe' => $currentWipe])
            ->andWhere(['key' => 'kills'])
            ->groupBy('steam_id')
            ->orderBy(['total_kills' => SORT_DESC])
            ->limit(1)
            ->asArray()
            ->one();

        if (!empty($topKills) && $topKills['steam_id'] === $steamId) {
            return 'Чемпион (TOP-1 киллов)';
        }

        return null;
    }

    /**
     * Получить количество обращений в поддержку
     * @param int $userId
     * @return int
     */
    protected function getUserSupportCount($userId)
    {
        return (int)Support::find()
            ->andWhere(['user_id' => $userId])
            ->count();
    }

    /**
     * Получить количество репортов
     * @param string $steamId
     * @return int
     */
    protected function getUserReportsCount($steamId)
    {
        return (int)Reports::find()
            ->andWhere(['steam_id' => $steamId])
            ->count();
    }

    /**
     * Получить короткое название типа вайпа
     * @param int $wipeType
     * @return string|null
     */
    protected function getWipeTypeSuffix($wipeType)
    {
        if ($wipeType === 7) {
            return ' (недельный)';
        }
        if ($wipeType === 14) {
            return ' (двухнедельный)';
        }
        if ($wipeType === 30) {
            return ' (месячный)';
        }
        return '';
    }

    /**
     * Получить роль текущего сервера пользователя
     * @param User $user
     * @return string|null Название роли в формате "Сервер: {monitoring_name} (тип вайпа)" или null
     */
    protected function getUserCurrentServerRole($user)
    {
        // Проверяем, есть ли у пользователя текущий сервер
        if (empty($user->server_id)) {
            return null;
        }

        // Получаем сервер по ID
        $server = Servers::findOne($user->server_id);
        
        if (empty($server) || $server->status !== Servers::STATUS_ACTIVE) {
            return null;
        }

        // Проверяем, что у сервера есть monitoring_name
        if (empty($server->monitoring_name)) {
            return null;
        }

        $wipeTypeSuffix = $this->getWipeTypeSuffix($server->wipe_type);
        return 'Сервер: ' . $server->monitoring_name . $wipeTypeSuffix;
    }

    /**
     * Обновить роли пользователя
     * @param User $user
     * @param string $guildId
     * @param string $botToken
     * @param DiscordRoles $discordRoles
     * @param array $currentRoleNames
     * @param array $shouldHaveRoles
     */
    protected function updateUserRoles($user, $guildId, $botToken, $discordRoles, $currentRoleNames, $shouldHaveRoles)
    {
        // Список всех ролей категории (для определения, какие роли нужно удалить)
        $categoryRoleNames = [];
        
        // Роли по часам игры
        foreach (self::ROLES_PLAYTIME as $role) {
            $categoryRoleNames[] = $role['name'];
        }
        
        // Роли по киллам
        foreach (self::ROLES_KILLS as $role) {
            $categoryRoleNames[] = $role['name'];
        }
        $categoryRoleNames[] = 'Чемпион (TOP-1 киллов)'; // TOP-1 киллов за вайп
        
        // Роли по вайпам
        foreach (self::ROLES_WIPES as $role) {
            $categoryRoleNames[] = $role['name'];
        }
        
        // Роли по поддержке
        foreach (self::ROLES_SUPPORT as $role) {
            $categoryRoleNames[] = $role['name'];
        }
        
        // Роли по репортам
        foreach (self::ROLES_REPORTS as $role) {
            $categoryRoleNames[] = $role['name'];
        }
        
        // Специальные роли
        $categoryRoleNames[] = self::ROLE_VIP;
        
        // Роли по серверам (добавляем все возможные роли серверов)
        $servers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->andWhere(['IS NOT', 'monitoring_name', null])
            ->andWhere(['<>', 'monitoring_name', ''])
            ->all();
        
        foreach ($servers as $server) {
            $wipeTypeSuffix = $this->getWipeTypeSuffix($server->wipe_type);
            $categoryRoleNames[] = 'Сервер: ' . $server->monitoring_name . $wipeTypeSuffix;
        }

        // Создаем обратный индекс: roleName => roleId из кэша
        $roleNameToId = [];
        if (isset(self::$guildRolesCache[$guildId])) {
            foreach (self::$guildRolesCache[$guildId] as $roleId => $roleName) {
                $roleNameToId[$roleName] = $roleId;
            }
        }

        // Удаляем старые роли категории, которых нет в shouldHaveRoles
        foreach ($currentRoleNames as $currentRoleName) {
            if (in_array($currentRoleName, $categoryRoleNames) && !in_array($currentRoleName, $shouldHaveRoles)) {
                if (isset($roleNameToId[$currentRoleName])) {
                    $roleId = $roleNameToId[$currentRoleName];
                    if ($discordRoles->removeRole($guildId, $user->discord_id, $roleId, $botToken)) {
                        Yii::info("Removed role {$currentRoleName} from user {$user->id}", __METHOD__);
                    }
                }
            }
        }

        // Выдаем новые роли
        foreach ($shouldHaveRoles as $roleName) {
            if (!in_array($roleName, $currentRoleNames)) {
                if (isset($roleNameToId[$roleName])) {
                    $roleId = $roleNameToId[$roleName];
                    if ($discordRoles->assignRole($guildId, $user->discord_id, $roleId, $botToken)) {
                        Yii::info("Assigned role {$roleName} to user {$user->id}", __METHOD__);
                    }
                } else {
                    Yii::warning("Role {$roleName} not found in Discord guild", __METHOD__);
                }
            }
        }
    }

    /**
     * Кэш ролей для гильдии
     * @var array
     */
    protected static $rolesCache = [];

    /**
     * Загрузить все роли гильдии в кэш
     * @param string $guildId
     * @param string $botToken
     */
    protected function loadGuildRoles($guildId, $botToken)
    {
        try {
            $response = (clone Yii::$app->curl)
                ->setOption(CURLOPT_TIMEOUT, 10)
                ->setHeader('Authorization', "Bot {$botToken}")
                ->get("https://discord.com/api/v10/guilds/{$guildId}/roles");

            $roles = json_decode($response, true);
            if (is_array($roles)) {
                self::$guildRolesCache[$guildId] = [];
                foreach ($roles as $role) {
                    if (isset($role['id']) && isset($role['name'])) {
                        self::$guildRolesCache[$guildId][$role['id']] = $role['name'];
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::error("Discord API error loading guild roles: " . $e->getMessage(), __METHOD__);
            self::$guildRolesCache[$guildId] = [];
        }
    }
}

