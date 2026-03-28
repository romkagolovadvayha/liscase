<?php

namespace common\components\queue\serverskin;

use common\models\rcon\RconTasks;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Выполняет skinbox.removeskin на серверах в фоне при отклонении скина.
 */
class ServerSkinRemoveSkinRconJob extends BaseObject implements JobInterface
{
    /** @var int Steam Workshop / published file id */
    public $skinId;

    /**
     * @param \yii\queue\Queue $queue
     */
    public function execute($queue)
    {
        $skinId = (int)$this->skinId;
        if ($skinId <= 0) {
            Yii::warning('ServerSkinRemoveSkinRconJob: invalid skinId', __METHOD__);
            return;
        }
        try {
            RconTasks::execute("skinbox.removeskin {$skinId}");
        } catch (\Throwable $e) {
            Yii::error('ServerSkinRemoveSkinRconJob: ' . $e->getMessage(), __METHOD__);
        }
    }
}
