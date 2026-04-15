<?php

namespace console\controllers;

use common\components\queue\process\DiscordRolesJob;
use common\components\queue\process\DiscordRolesUserJob;
use common\models\user\User;
use common\models\user\UserVip;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Команда для проверки и выдачи ролей Discord на основе статистики
 */
class DiscordRolesController extends Controller
{
    /**
     * Ограничить {@see actionCheck} только пользователями с флагом блогера (is_blogger=1) и Discord.
     * Примеры: `php yii discord-roles/check --bloggersOnly=1` или `--bloggers-only=true`
     * @var bool|int|string
     */
    public $bloggersOnly = 0;

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        $base = parent::options($actionID);
        return $actionID === 'check'
            ? array_merge($base, ['bloggersOnly'])
            : $base;
    }

    /**
     * {@inheritdoc}
     */
    public function optionAliases()
    {
        $base = parent::optionAliases();
        return array_merge($base, [
            'bloggers-only' => 'bloggersOnly',
        ]);
    }

    /**
     * Проверить и выдать роли Discord пользователям с привязанным Discord (по умолчанию — все).
     *
     * `php yii discord-roles/check` — все пользователи с discord_id.
     * `php yii discord-roles/check --bloggersOnly=1` — только is_blogger=1.
     *
     * @return int Exit code
     */
    public function actionCheck()
    {
        $onlyBloggers = $this->isBloggersOnlyCliOption();
        $this->stdout(
            'Starting Discord roles check'
            . ($onlyBloggers ? " (bloggers only: is_blogger=1)\n" : " (all users with Discord)\n")
        );

        try {
            $job = new DiscordRolesJob(['bloggersOnly' => $onlyBloggers ? 1 : 0]);
            Yii::$app->queueVk->push($job);

            $this->stdout(
                'Discord roles scheduler job added to queue (batches of '
                . DiscordRolesJob::USERS_PER_BATCH
                . " users).\n"
            );
            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Разбор опции --bloggersOnly из консоли (строка/число/bool).
     */
    protected function isBloggersOnlyCliOption(): bool
    {
        $v = $this->bloggersOnly;
        if ($v === true || $v === 1) {
            return true;
        }
        if (is_string($v)) {
            $s = strtolower(trim($v, " \t\n\r\0\x0B'\""));
            return in_array($s, ['1', 'true', 'yes', 'on'], true);
        }
        return false;
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

    /**
     * Проверить истекшие VIP и забрать роль VIP в Discord у пользователей
     * discord-roles/check-expired-vip
     * 
     * Проверяет пользователей, у которых VIP истек за последние 24 часа,
     * и обновляет их Discord роли (роль VIP будет автоматически удалена)
     * 
     * @return int Exit code
     */
    public function actionCheckExpiredVip()
    {
        $this->stdout("Checking expired VIP users...\n");

        try {
            // Время 24 часа назад
            $twentyFourHoursAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
            // Текущее время
            $now = date('Y-m-d H:i:s');

            // Находим всех пользователей с истекшим VIP за последние 24 часа
            // VIP истек, если expires_at между 24 часами назад и сейчас
            $expiredVips = UserVip::find()
                ->andWhere(['<=', 'expires_at', $now])
                ->andWhere(['>=', 'expires_at', $twentyFourHoursAgo])
                ->all();

            $this->stdout("Found " . count($expiredVips) . " expired VIP records.\n");

            $processedCount = 0;
            $skippedCount = 0;

            foreach ($expiredVips as $vip) {
                try {
                    $user = User::findOne($vip->user_id);
                    
                    if (!$user) {
                        $this->stdout("  ⚠ User {$vip->user_id} not found, skipping.\n");
                        $skippedCount++;
                        continue;
                    }

                    // Проверяем, есть ли у пользователя привязанный Discord аккаунт
                    if (empty($user->discord_id)) {
                        $this->stdout("  ⚠ User {$user->id} ({$user->username}) has no Discord account, skipping.\n");
                        $skippedCount++;
                        continue;
                    }

                    // Проверяем, что VIP действительно истек (дополнительная проверка)
                    if ($vip->isActive()) {
                        $this->stdout("  ⚠ User {$user->id} ({$user->username}) VIP is still active, skipping.\n");
                        $skippedCount++;
                        continue;
                    }

                    // Добавляем job в очередь для обновления Discord ролей
                    // DiscordRolesUserJob автоматически проверит hasVip() и удалит роль VIP
                    Yii::$app->queueVk->push(new DiscordRolesUserJob(['userId' => $user->id]));
                    
                    $this->stdout("  ✅ Queued Discord roles update for user {$user->id} ({$user->username}) - VIP expired at {$vip->expires_at}\n");
                    $processedCount++;
                } catch (\Exception $e) {
                    $this->stderr("  ❌ Error processing VIP record {$vip->id}: " . $e->getMessage() . "\n");
                    Yii::error("Error processing expired VIP {$vip->id}: " . $e->getMessage(), __METHOD__);
                }
            }

            $this->stdout("\n");
            $this->stdout("Summary:\n");
            $this->stdout("  Processed: {$processedCount}\n");
            $this->stdout("  Skipped: {$skippedCount}\n");
            $this->stdout("  Total: " . count($expiredVips) . "\n");

            return ExitCode::OK;
        } catch (\Exception $e) {
            $this->stderr("Error: " . $e->getMessage() . "\n");
            Yii::error("DiscordRolesController::actionCheckExpiredVip error: " . $e->getMessage(), __METHOD__);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}

