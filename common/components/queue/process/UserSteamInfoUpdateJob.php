<?php

namespace common\components\queue\process;

use common\components\helpers\CacheArrayHelper;
use common\components\oauth\Steam;
use common\models\user\User;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\helpers\HtmlPurifier;
use yii\queue\JobInterface;

class UserSteamInfoUpdateJob extends BaseObject implements JobInterface
{

    public $steamId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $cacheKey = "steamIds_for_update";
            /** @var User $user */
            $user = User::find()
                        ->andWhere(['steam_id' => $this->steamId])
                        ->one();
            if (!empty($user) && strtotime($user->updated_at) < time() - 5 * 60) {
                CacheArrayHelper::withLock($cacheKey, function() use ($cacheKey) {
                    CacheArrayHelper::addToCacheArray($cacheKey, $this->steamId);
                });
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('UserSteamInfoUpdateJob: ' . $ex->getMessage());
        }
    }
}