<?php

namespace common\components\queue\process;

use common\components\discord\DiscordRoles;
use common\models\user\User;
use Yii;

/**
 * Одна порция пользователей с привязанным Discord; ставится в очередь из {@see DiscordRolesJob}.
 */
class DiscordRolesBatchJob extends DiscordRolesJob
{
    /** @var int[] */
    public $userIds = [];

    /**
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        if ($this->userIds === []) {
            Yii::warning('DiscordRolesBatchJob: empty userIds', __METHOD__);
            return;
        }

        try {
            $guildId = Yii::$app->settings->get('discord_guild_id');
            $botToken = Yii::$app->settings->get('discord_bot_token');

            if (empty($guildId) || empty($botToken)) {
                Yii::error('Discord guild ID or bot token not configured', __METHOD__);
                return;
            }

            $discordRoles = new DiscordRoles();

            if (!$this->ensureRolesExist($guildId, $botToken, $discordRoles)) {
                Yii::error('DiscordRolesBatchJob: ensureRolesExist aborted (guild roles unavailable)', __METHOD__);
                return;
            }

            self::resetRuntimeCaches();

            $users = User::find()
                ->andWhere(['id' => $this->userIds])
                ->all();

            $serverIds = [];
            foreach ($users as $u) {
                if (!empty($u->server_id)) {
                    $serverIds[] = (int)$u->server_id;
                }
            }
            self::warmServersForBatch($serverIds);

            Yii::info(
                'DiscordRolesBatchJob: loaded ' . count($users) . ' user(s) for batch of ' . count($this->userIds) . ' id(s)',
                __METHOD__
            );

            $processed = 0;
            foreach ($users as $user) {
                if ($processed % self::DB_RECONNECT_EVERY_USERS === 0) {
                    $this->reconnectDb();
                }
                ++$processed;

                try {
                    $this->processUserRoles($user, $guildId, $botToken, $discordRoles);
                } catch (\Throwable $e) {
                    if ($this->isMysqlConnectionLost($e)) {
                        Yii::warning(
                            "MySQL connection lost while processing user {$user->id}, reconnecting: " . $e->getMessage(),
                            __METHOD__
                        );
                        $this->reconnectDb();
                        try {
                            $this->processUserRoles($user, $guildId, $botToken, $discordRoles);
                        } catch (\Throwable $e2) {
                            Yii::error(
                                "Error processing user {$user->id} after DB reconnect: " . $e2->getMessage(),
                                __METHOD__
                            );
                        }
                    } else {
                        Yii::error("Error processing user {$user->id}: " . $e->getMessage(), __METHOD__);
                    }
                }
            }

            Yii::info('DiscordRolesBatchJob: batch completed', __METHOD__);
        } catch (\Exception $ex) {
            Yii::error(
                'DiscordRolesBatchJob error: ' . $ex->getFile() . ':' . $ex->getLine() . ' - ' . $ex->getMessage(),
                __METHOD__
            );
            if (method_exists(Yii::$app, 'telegramChats')) {
                Yii::$app->telegramChats->sendMessage(
                    'DiscordRolesBatchJob: ' . PHP_EOL . $ex->getFile() . ': ' . $ex->getLine() . PHP_EOL . $ex->getMessage()
                );
            }
        }
    }
}
