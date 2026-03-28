<?php

namespace common\components\queue\serverskin;

use common\models\rcon\RconTasks;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Выполняет skinbox.addskin на серверах в фоне, чтобы принятие скина в админке не ждало все RCON-запросы.
 */
class ServerSkinAddSkinRconJob extends BaseObject implements JobInterface
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
            Yii::warning('ServerSkinAddSkinRconJob: invalid skinId', __METHOD__);
            return;
        }
        try {
            RconTasks::execute("skinbox.addskin {$skinId}");
        } catch (\Throwable $e) {
            Yii::error('ServerSkinAddSkinRconJob: ' . $e->getMessage(), __METHOD__);
        }
    }
}
