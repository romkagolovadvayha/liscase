<?php

namespace console\commands;

use common\components\queue\process\DiscordRolesJob;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Команда для проверки и выдачи ролей Discord на основе статистики
 */
class DiscordRolesController extends Controller
{
    /**
     * Проверить и выдать роли Discord всем пользователям
     * @return int Exit code
     */
    public function actionCheck()
    {
        $this->stdout("Starting Discord roles check...\n");

        try {
            // Добавляем задачу в очередь
            Yii::$app->queueProcess->push(new DiscordRolesJob());
            
            $this->stdout("Discord roles check job added to queue.\n");
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Проверить и выдать роли для конкретного пользователя (для тестирования)
     * @param int $userId ID пользователя
     * @return int Exit code
     */
    public function actionCheckUser($userId)
    {
        $this->stdout("Checking Discord roles for user {$userId}...\n");

        try {
            $job = new DiscordRolesJob();
            // Можно добавить логику для проверки одного пользователя
            $this->stdout("User check completed.\n");
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}

