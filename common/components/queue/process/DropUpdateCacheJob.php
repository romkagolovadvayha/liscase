<?php

namespace common\components\queue\process;

use common\models\box\Drop;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class DropUpdateCacheJob extends BaseObject implements JobInterface
{
    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     */
    public function execute($queue)
    {
        try {
            Drop::updateCache();
        } catch (\Exception $e) {
            Yii::error('DropUpdateCacheJob: ' . $e->getMessage(), __METHOD__);
        }
    }
}
