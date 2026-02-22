<?php

namespace common\components\queue\process;

use common\models\map\Map;
use common\models\servers\Servers;
use common\models\user\UserDrop;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class BuyDropJob extends BaseObject implements JobInterface
{
    /**
     * @var UserDrop
     */
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
            $id = $userDrop instanceof UserDrop ? $userDrop->id : ($userDrop['id'] ?? null);
            if (!$id) {
                return;
            }
            $userDrop = UserDrop::find()->where(['id' => $id])->with(['drop', 'user'])->one();
            if (!$userDrop || empty($userDrop->drop) || empty($userDrop->user)) {
                return;
            }

            $productHtml = '';
            try {
                $productHtml = Yii::$app->view->renderFile(Yii::getAlias('@frontend/views/store') . '/_product.php', [
                    'drop' => $userDrop->drop[0],
                    'serverId' => $userDrop->user->server_id,
                    'userDrop' => $userDrop,
                ]);
            } catch (\Throwable $e) {
                // консоль/воркер может не иметь @frontend — тогда ChatServer отправит без product или по старой логике
            }

            $cacheKey = 'ws_buy_drop_' . $userDrop->user_id . '_' . $userDrop->id;
            $data = [
                'action' => 'buyDrop',
                'code' => 200,
                'id' => $userDrop->id,
                'timestamp' => time(),
            ];
            if ($productHtml !== '') {
                $data['product'] = $productHtml;
            }
            Yii::$app->cache->set($cacheKey, $data, 30);

            $listKey = 'ws_drops_list_' . $userDrop->user_id;
            $dropsList = Yii::$app->cache->get($listKey) ?: [];
            if (!in_array($userDrop->id, $dropsList)) {
                $dropsList[] = $userDrop->id;
                Yii::$app->cache->set($listKey, $dropsList, 60);
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('BuyDropJob ERROR: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }

}