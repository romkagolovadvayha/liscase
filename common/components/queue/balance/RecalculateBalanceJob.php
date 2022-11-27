<?php

namespace common\components\queue\balance;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class RecalculateBalanceJob extends BaseObject implements JobInterface
{
    public $userBalanceId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        if (!$this->userBalanceId) {
            return;
        }

        //test
    }
}