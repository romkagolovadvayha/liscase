<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\models\rcon\RconTasks;
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
        try {
            $request = json_decode($this->data, 1);
            $approvedIds = $request['approved_ids'];

            Yii::$app->settings->set('custom-skins_approved_list', implode(',', $request['approved_ids']));

            /** @var ServerSkin[] $skins */
            $skins = ServerSkin::find()
                               ->andWhere(['status' => ServerSkin::STATUS_ACTIVE])
                               ->all();

            $count = 0;
            foreach ($skins as $skin) {
                if (!in_array($skin->skin_id, $approvedIds)) {
                    continue;
                }
                $count++;

                $skin->status = ServerSkin::STATUS_REJECT;
                $skin->save(false);

                RconTasks::execute("skinbox.removeskin {$skin->skin_id}");
                $message = "🟢 <b>Скин автоматически удален</b>" . PHP_EOL . "Skin ID: {$skin->skin_id}" . PHP_EOL
                    . "Причина: Принят в игру";

                Yii::$app->telegramSupport->sendMessage($message, [], $skin->getImagePubUrl());

                sleep(3);
            }

            Yii::$app->telegramSupport->sendMessage("Всего отклонено скинов: {$count}");

        } catch (\Throwable $e) {
            Yii::error("SkinsApprovedJob: " . $e->getMessage(), 'error');
        }
    }
}