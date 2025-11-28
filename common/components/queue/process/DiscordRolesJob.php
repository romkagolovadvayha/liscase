<?php

namespace common\components\queue\process;

use common\components\discord\DiscordRoles;
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
    // Роли по часам игры (playtime)
    const ROLES_PLAYTIME = [
        ['name' => 'Новобранец Пустошей', 'min' => 0, 'max' => 100],
        ['name' => 'Опытный Выживший', 'min' => 100, 'max' => 500],
        ['name' => 'Ветеран Ржавчины', 'min' => 500, 'max' => 1000],
        ['name' => 'Легенда Пустошей', 'min' => 1000, 'max' => 5000],
        ['name' => 'Мастер Выживания', 'min' => 5000, 'max' => 10000],
        ['name' => 'Бессмертный Ржавчины', 'min' => 10000, 'max' => PHP_INT_MAX],
    ];

    // Роли по киллам
    const ROLES_KILLS = [
        ['name' => 'Стрелок', 'min' => 0, 'max' => 500],
        ['name' => 'Охотник', 'min' => 500, 'max' => 1000],
        ['name' => 'Хищник Пустошей', 'min' => 1000, 'max' => 3000],
        ['name' => 'Гроза Новичков', 'min' => 3000, 'max' => 5000],
        ['name' => 'Маньяк ПвП', 'min' => 5000, 'max' => 10000],
        ['name' => 'Уничтожитель', 'min' => 10000, 'max' => PHP_INT_MAX],
    ];

    // Роли по вайпам
    const ROLES_WIPES = [
        ['name' => 'Выживший Вайпа', 'min' => 1, 'max' => 10],
        ['name' => 'Старожил Серверов', 'min' => 10, 'max' => 25],
        ['name' => 'Ветеран Вайпов', 'min' => 25, 'max' => 50],
        ['name' => 'Железный Нерф', 'min' => 50, 'max' => 100],
        ['name' => 'Разрушитель Эпох', 'min' => 100, 'max' => 200],
        ['name' => 'Архивариус Вайпов', 'min' => 200, 'max' => PHP_INT_MAX],
    ];

    // Роли по фарму (можно получить несколько ролей одновременно, но ограничиваем 3)
    // Примечание: проверяются все роли, но выдаются только те, которые соответствуют критериям

    // Роли по поддержке
    const ROLES_SUPPORT = [
        ['name' => 'Помощник', 'min' => 1, 'max' => 10],
        ['name' => 'Активный Помощник', 'min' => 10, 'max' => 50],
        ['name' => 'Защитник Сообщества', 'min' => 50, 'max' => PHP_INT_MAX],
    ];

    // Роли по репортам
    const ROLES_REPORTS = [
        ['name' => 'Информатор', 'min' => 1, 'max' => 10],
        ['name' => 'Сыщик', 'min' => 10, 'max' => 50],
        ['name' => 'Страж Порядка', 'min' => 50, 'max' => PHP_INT_MAX],
    ];

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
    protected function ensureRolesExist($guildId, $botToken, $discordRoles)
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
        $allRoles[] = 'Хозяин Арены';
        foreach (self::ROLES_WIPES as $role) {
            $allRoles[] = $role['name'];
        }
        $allRoles[] = 'Лесоруб';
        $allRoles[] = 'Шахтёр';
        $allRoles[] = 'Фермер Руды';
        $allRoles[] = 'Технарь';
        $allRoles[] = 'Нефтяной Барон';
        $allRoles[] = 'Торговец Пустошей';
        $allRoles[] = 'Король Фермы';
        foreach (self::ROLES_SUPPORT as $role) {
            $allRoles[] = $role['name'];
        }
        foreach (self::ROLES_REPORTS as $role) {
            $allRoles[] = $role['name'];
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
    protected function processUserRoles($user, $guildId, $botToken, $discordRoles)
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
        
        // Проверяем TOP-1 киллов за вайп (уникальная роль)
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

        // Роли по фарму
        $farmRoles = $this->getUserFarmRoles($user->steam_id);
        $shouldHaveRoles = array_merge($shouldHaveRoles, $farmRoles);

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

        return (int)($totalSeconds / 3600); // Конвертируем секунды в часы
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
     * Получить роли по фарму
     * @param string $steamId
     * @return array
     */
    protected function getUserFarmRoles($steamId)
    {
        $roles = [];
        
        // Получаем суммарные значения ресурсов за все время
        $wood = (int)Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'wood'])
            ->sum('value') ?: 0;
            
        $stones = (int)Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'stones'])
            ->sum('value') ?: 0;
            
        $sulfur = (int)Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'sulfur.ore'])
            ->sum('value') ?: 0;
            
        $metalHq = (int)Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'metal.hq.ore'])
            ->sum('value') ?: 0;
            
        $fuel = (int)Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'lowgradefuel'])
            ->sum('value') ?: 0;
        $fuel += (int)Statistics::find()
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['key' => 'highgradefuel'])
            ->sum('value') ?: 0;
        
        $totalResources = $wood + $stones + $sulfur + $metalHq + $fuel;
        
        // Собираем все возможные роли по фарму с приоритетами
        $farmRolesWithPriority = [];
        
        if ($wood >= 100000) {
            $farmRolesWithPriority[] = ['name' => 'Лесоруб', 'priority' => 1];
        }
        if ($stones >= 100000) {
            $farmRolesWithPriority[] = ['name' => 'Шахтёр', 'priority' => 1];
        }
        if ($sulfur >= 100000) {
            $farmRolesWithPriority[] = ['name' => 'Фермер Руды', 'priority' => 1];
        }
        if ($metalHq >= 10000) {
            $farmRolesWithPriority[] = ['name' => 'Технарь', 'priority' => 2];
        }
        if ($fuel >= 1000000) {
            $farmRolesWithPriority[] = ['name' => 'Нефтяной Барон', 'priority' => 3];
        }
        if ($totalResources >= 1000000) {
            $farmRolesWithPriority[] = ['name' => 'Торговец Пустошей', 'priority' => 3];
        }
        
        // Проверяем TOP-1 по ресурсам (высший приоритет)
        $topResourceRole = $this->getTopResourceRole($steamId);
        if ($topResourceRole) {
            $farmRolesWithPriority[] = ['name' => $topResourceRole, 'priority' => 4];
        }
        
        // Сортируем по приоритету и берем только 3 самые высокие
        usort($farmRolesWithPriority, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        $farmRolesWithPriority = array_slice($farmRolesWithPriority, 0, 3);
        
        foreach ($farmRolesWithPriority as $role) {
            $roles[] = $role['name'];
        }

        return $roles;
    }

    /**
     * Получить роль TOP-1 по ресурсам
     * @param string $steamId
     * @return string|null
     */
    protected function getTopResourceRole($steamId)
    {
        // Получаем топ игроков по суммарным ресурсам
        $topUsers = Statistics::find()
            ->select([
                'steam_id',
                'SUM(CASE WHEN `key` IN ("wood", "stones", "sulfur.ore", "metal.hq.ore", "lowgradefuel", "highgradefuel") THEN value ELSE 0 END) as total_resources'
            ])
            ->groupBy('steam_id')
            ->orderBy(['total_resources' => SORT_DESC])
            ->limit(1)
            ->asArray()
            ->one();

        if (!empty($topUsers) && $topUsers['steam_id'] === $steamId) {
            return 'Король Фермы';
        }

        return null;
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
            return 'Хозяин Арены';
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
        $categoryRoleNames[] = 'Хозяин Арены'; // TOP-1 киллов
        
        // Роли по вайпам
        foreach (self::ROLES_WIPES as $role) {
            $categoryRoleNames[] = $role['name'];
        }
        
        // Роли по фарму
        $categoryRoleNames[] = 'Лесоруб';
        $categoryRoleNames[] = 'Шахтёр';
        $categoryRoleNames[] = 'Фермер Руды';
        $categoryRoleNames[] = 'Технарь';
        $categoryRoleNames[] = 'Нефтяной Барон';
        $categoryRoleNames[] = 'Торговец Пустошей';
        $categoryRoleNames[] = 'Король Фермы'; // TOP-1 ресурсов
        
        // Роли по поддержке
        foreach (self::ROLES_SUPPORT as $role) {
            $categoryRoleNames[] = $role['name'];
        }
        
        // Роли по репортам
        foreach (self::ROLES_REPORTS as $role) {
            $categoryRoleNames[] = $role['name'];
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

