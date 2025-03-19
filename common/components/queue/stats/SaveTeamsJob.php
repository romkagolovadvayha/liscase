<?php

namespace common\components\queue\stats;

use common\components\queue\telegram\SendMessageJob;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SaveTeamsJob extends BaseObject implements JobInterface
{
    public $data;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            Yii::$app->telegramChats->sendMessage(json_encode($_SERVER));
            Yii::$app->telegramChats->sendMessage($this->data);
            $request = json_decode($this->data, 1);
            /** @var Servers $server */
            $server = Servers::find()
                             ->cache(60)
                             ->andWhere(['ip' => $request['ip']])
                             ->andWhere(['port' => $request['port']])
                             ->one();
            if (empty($server)) {
                return;
            }
            if (!empty($request['teams'])) {
                foreach ($request['teams'] as $item) {
                    try {

                    } catch (\Exception $e) {
                        Yii::$app->telegramChats->sendMessage($this->data);
                        Yii::$app->telegramChats->sendMessage("SaveRaidJob foreach: " . $e->getLine() . ":" . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage($this->data);
            Yii::$app->telegramChats->sendMessage("SaveRaidJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }
}