<?php

namespace common\components\queue\process;

use common\components\discord\DiscordRoles;
use common\components\queue\process\DiscordRolesJob;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Job для проверки и выдачи ролей Discord для одного пользователя
 */
class DiscordRolesUserJob extends BaseObject implements JobInterface
{
    /**
     * @var int ID пользователя
     */
    public $userId;

    /**
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        try {
            if (empty($this->userId)) {
                Yii::error('DiscordRolesUserJob: userId is empty', __METHOD__);
                return;
            }

            $user = User::findOne($this->userId);
            if (!$user) {
                Yii::error("DiscordRolesUserJob: User {$this->userId} not found", __METHOD__);
                return;
            }

            if (empty($user->discord_id)) {
                Yii::info("DiscordRolesUserJob: User {$this->userId} has no discord_id", __METHOD__);
                return;
            }

            $guildId = Yii::$app->settings->get('discord_guild_id');
            $botToken = Yii::$app->settings->get('discord_bot_token');

            if (empty($guildId) || empty($botToken)) {
                Yii::error('Discord guild ID or bot token not configured', __METHOD__);
                return;
            }

            $discordRoles = new DiscordRoles();

            // Создаем роли в Discord, если их нет (используем метод из DiscordRolesJob)
            $mainJob = new DiscordRolesJob();
            $mainJob->ensureRolesExist($guildId, $botToken, $discordRoles);

            // Обрабатываем роли для пользователя
            $mainJob->processUserRoles($user, $guildId, $botToken, $discordRoles);

            Yii::info("Discord roles check completed for user {$this->userId}", __METHOD__);
        } catch (\Exception $ex) {
            Yii::error("DiscordRolesUserJob error: " . $ex->getFile() . ":" . $ex->getLine() . " - " . $ex->getMessage(), __METHOD__);
            if (method_exists(Yii::$app, 'telegramChats')) {
                Yii::$app->telegramChats->sendMessage('DiscordRolesUserJob: ' . PHP_EOL . $ex->getFile() . ": " . $ex->getLine() . PHP_EOL . $ex->getMessage());
            }
        }
    }
}

