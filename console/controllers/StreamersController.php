<?php

namespace console\controllers;

use common\components\StreamersLiveHelper;
use common\models\media\MediaLive;
use common\models\user\User;
use Yii;
use yii\console\Controller;

/**
 * Крон: обновление статуса «в эфире» для стримеров (is_blogger).
 * Записывает stream_live_at при обнаружении стрима в Twitch/Kick и ведёт таблицу media_live.
 *
 * Запуск: php yii streamers/update-live-status
 * Crontab (каждые 3 мин): php yii streamers/update-live-status
 */
class StreamersController extends Controller
{
    /**
     * Проверяет Twitch/Kick для всех стримеров и записывает stream_live_at при обнаружении в эфире.
     * Создаёт/закрывает записи в media_live (не более одной активной сессии на пользователя).
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
            $platform = null;
            if ($user->twitch_id) {
                if (StreamersLiveHelper::isTwitchLive((string) $user->twitch_id)) {
                    $platform = MediaLive::PLATFORM_TWITCH;
                }
            }
            if ($platform === null && $user->kick_id) {
                if (StreamersLiveHelper::isKickLive((string) $user->kick_id)) {
                    $platform = MediaLive::PLATFORM_KICK;
                }
            }

            $tx = Yii::$app->db->beginTransaction();
            try {
                $active = MediaLive::ensureSingleActiveSession((int) $user->id, $now);

                if ($platform !== null) {
                    if ($active === null) {
                        $live = new MediaLive();
                        $live->user_id = (int) $user->id;
                        $live->started_at = $now;
                        $live->status = MediaLive::STATUS_LIVE;
                        $live->platform = $platform;
                        $live->created_at = $now;
                        $live->save(false);
                    } elseif ($active->platform !== $platform) {
                        $active->finalize($now, true);
                        $live = new MediaLive();
                        $live->user_id = (int) $user->id;
                        $live->started_at = $now;
                        $live->status = MediaLive::STATUS_LIVE;
                        $live->platform = $platform;
                        $live->created_at = $now;
                        $live->save(false);
                    }
                } elseif ($active !== null) {
                    $active->finalize($now, true);
                }

                $tx->commit();
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::error('Streamers update-live-status user ' . $user->id . ': ' . $e->getMessage(), __METHOD__);
                continue;
            }

            $isLive = $platform !== null;
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
