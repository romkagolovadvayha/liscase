<?php

namespace common\components\queue\stats;

use common\components\queue\telegram\SendMessageJob;
use common\models\building\Signs;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SaveSignsJob extends BaseObject implements JobInterface
{
    public $data;
    public $ip;

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

            if ($this->ip != $request['ip']) {
                return;
            }

            /** @var Servers $server */
            $server = Servers::find()
                             ->cache(60)
                             ->andWhere(['ip' => $request['ip']])
                             ->andWhere(['port' => $request['port']])
                             ->one();
            if (empty($server)) {
                return;
            }
            if (!empty($request['signs'])) {
                foreach ($request['signs'] as $item) {
                    try {
                        $model = Signs::find()
                            ->andWhere(['signId' => $item['signId']])
                            ->andWhere(['server_id' => $server->id])
                            ->one();

                        if (empty($model)) {
                            $model = new Signs();
                            $user = User::findBySteamId($item['steamId']);
                            $model->user_id = $user->id;
                            $model->server_id = $server->id;
                            $model->signId = $item['signId'];
                            $model->status = Signs::STATUS_ACTIVE;
                        }
                        $fileName = "{$model->user->steam_id}_{$item['signId']}.png";
                        $filePath = $this->_loadImage($item['base64Image'], $server->tag, $fileName);
                        $model->image = $filePath;
                        $model->position = $item['position'];
                        $model->type = $item['type'];
                        $model->created_at = date('Y-m-d H:i:s');
                        $model->save();
                        unset($item['base64Image']);
                        Yii::$app->telegramChats->sendMessage(json_encode($item));
                    } catch (\Exception $e) {
                        Yii::$app->telegramChats->sendMessage("SaveRaidJob foreach: " . $e->getLine() . ":" . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("SaveRaidJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }

    private function _loadImage($file, $serverTag, $fileName) {
        if (empty($file)) {
            return null;
        }
        $uploadDir = Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/signs/{$serverTag}/{$fileName}";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, $file);
        return $fileUrl;
    }
}