<?php

namespace console\controllers;

use common\components\StreamersLiveHelper;
use common\models\user\User;
use Yii;
use yii\console\Controller;

/**
 * Крон: обновление статуса «в эфире» для стримеров (is_blogger).
 * Записывает stream_live_at при обнаружении стрима в Twitch/Kick.
 *
 * Запуск: php yii streamers/update-live-status
 * Crontab (каждые 3 мин): php yii streamers/update-live-status
 */
class StreamersController extends Controller
{
    /**
     * Проверяет Twitch/Kick для всех стримеров и записывает stream_live_at при обнаружении в эфире.
     */
    public function actionUpdateLiveStatus()
    {
        $query = User::find()
            ->joinWith(['userProfile'])
            ->where(['user.is_blogger' => 1])
            ->andWhere(
                '(user_profile.twitch_link IS NOT NULL AND TRIM(COALESCE(user_profile.twitch_link, "")) != "")' .
                ' OR (user_profile.kick_link IS NOT NULL AND TRIM(COALESCE(user_profile.kick_link, "")) != "")' .
                ' OR (user.twitch_id IS NOT NULL AND user.twitch_id != "")' .
                ' OR (user.kick_id IS NOT NULL AND user.kick_id != "")'
            );

        $users = $query->all();
        $now = date('Y-m-d H:i:s');
        $updated = 0;

        foreach ($users as $user) {
            $isLive = false;
            if ($user->twitch_id) {
                $isLive = StreamersLiveHelper::isTwitchLive((string) $user->twitch_id);
            }
            if (!$isLive && $user->kick_id) {
                $isLive = StreamersLiveHelper::isKickLive((string) $user->kick_id);
            }
            if ($isLive && $user->stream_live_at !== $now) {
                $user->stream_live_at = $now;
                if ($user->save(false, ['stream_live_at'])) {
                    $updated++;
                }
            }
        }

        return 0;
    }
}
