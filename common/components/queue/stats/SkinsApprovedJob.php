<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\models\serverskin\ServerSkin;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SkinsApprovedJob extends BaseObject implements JobInterface
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
        Yii::$app->telegramChats->sendMessage(1);
        try {
            $request = json_decode($this->data, 1);
            Yii::$app->telegramChats->sendMessage(2);
            $approvedIds = $request['approved_ids'];
            Yii::$app->telegramChats->sendMessage(3);

            Yii::$app->telegramChats->sendMessage(count($approvedIds));
            /** @var ServerSkin[] $skins */
            $skins = ServerSkin::find()
                               ->andWhere(['status' => ServerSkin::STATUS_ACTIVE])
                               ->all();

            Yii::$app->telegramChats->sendMessage(count($skins));
            $count = 0;
            foreach ($skins as $skin) {
                if (!in_array($skin->skin_id, $approvedIds)) {
                    continue;
                }
                $count++;

                if ($count < 10) {
                    $message = "🟢 <b>Скин автоматически удален</b>" . PHP_EOL . "Skin ID: {$skin->skin_id}" . PHP_EOL
                        . "Причина: Принят в игру";

                    Yii::$app->telegramSupport->sendMessage($message, [], $skin->getImagePubUrl());
                }
            }

            Yii::$app->telegramSupport->sendMessage("Всего отклонено скинов: {$count}");

        } catch (\Throwable $e) {
            Yii::error("SkinsApprovedJob: " . $e->getMessage(), 'error');
        }
    }
}