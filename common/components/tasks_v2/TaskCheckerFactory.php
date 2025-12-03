<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use Yii;

/**
 * Фабрика для создания проверщиков заданий
 */
class TaskCheckerFactory
{
    /**
     * Создать проверщик для задания
     * @param TaskV2 $task
     * @return TaskCheckerInterface
     * @throws \Exception
     */
    public static function create(TaskV2 $task): TaskCheckerInterface
    {
        $checkType = $task->check_type;
        $checkerClass = self::getCheckerClass($checkType);

        if (!class_exists($checkerClass)) {
            throw new \Exception("Checker class not found: {$checkerClass}");
        }

        $checker = Yii::createObject($checkerClass);
        
        if (!$checker instanceof TaskCheckerInterface) {
            throw new \Exception("Checker must implement TaskCheckerInterface: {$checkerClass}");
        }

        return $checker;
    }

    /**
     * Получить класс проверщика по типу проверки
     * @param string $checkType
     * @return string
     */
    protected static function getCheckerClass($checkType)
    {
        $classes = [
            TaskV2::CHECK_TYPE_VK_SUBSCRIBE_GROUP => VkSubscribeGroupChecker::class,
            TaskV2::CHECK_TYPE_TELEGRAM_CONNECTED => TelegramConnectedChecker::class,
            TaskV2::CHECK_TYPE_TELEGRAM_CHANNEL_SUBSCRIBE => TelegramChannelSubscribeChecker::class,
            TaskV2::CHECK_TYPE_DISCORD_JOIN => DiscordJoinChecker::class,
            TaskV2::CHECK_TYPE_KILL_BOTS_COUNT => KillBotsCountChecker::class,
            TaskV2::CHECK_TYPE_INVITE_FRIEND => InviteFriendChecker::class,
            TaskV2::CHECK_TYPE_CUSTOM_MANUAL => CustomManualChecker::class,
            TaskV2::CHECK_TYPE_DAILY_REWARD => DailyRewardChecker::class,
            TaskV2::CHECK_TYPE_STATISTICS_PARAM => StatisticsParamChecker::class,
            TaskV2::CHECK_TYPE_COMMENTS_COUNT => CommentsCountChecker::class,
            TaskV2::CHECK_TYPE_BUILDING_ADD => BuildingAddChecker::class,
            TaskV2::CHECK_TYPE_RADIO_TRACK_ADD => RadioTrackAddChecker::class,
        ];

        return $classes[$checkType] ?? CustomManualChecker::class;
    }
}



