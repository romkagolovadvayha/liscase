<?php

namespace common\components\queue\process;

use common\models\map\Map;
use common\models\servers\Servers;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class ActivatedDropJob extends BaseObject implements JobInterface
{
    public $userDrop;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $userDrop = $this->userDrop;
            $client = new Client(Yii::$app->params['ws']);
            if ($userDrop->save()) {
                $client->send(
                    json_encode(
                        [
                            'action' => 'activatedDrop',
                            'code'   => 200,
                            'id'     => $userDrop->id,
                        ]
                    )
                );
            } else {
                $client->send(
                    json_encode(
                        [
                            'action' => 'activatedDrop',
                            'code'   => 500,
                            'message'   => Yii::t(
                                'common',
                                "Произошла ошибка при получении товара, попробуйте позже!",
                                [],
                                $userDrop->user->current_language
                            ),
                            'id'     => $userDrop->id,
                        ]
                    )
                );
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('ApiController: ' . $ex->getMessage());
        }
    }

}