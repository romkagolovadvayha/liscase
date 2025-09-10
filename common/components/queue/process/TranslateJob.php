<?php

namespace common\components\queue\process;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class TranslateJob extends BaseObject implements JobInterface
{

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            \Yii::$app->runAction('translate/import-api');
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('TranslateJob: ' . PHP_EOL . $ex->getFile() . ": " . $ex->getLine() . PHP_EOL . $ex->getMessage());
        }
    }
}