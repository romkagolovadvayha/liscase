<?php

namespace common\components\queue\process;

use common\models\user\UserDrop;
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
     * Рендер в воркере, чтобы ChatServer не блокировался на рендере.
     *
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        try {
            $userDrop = UserDrop::find()
                ->where(['id' => $this->userDrop->id])
                ->with(['drop', 'user'])
                ->one();
            if (!$userDrop || !$userDrop->drop || !$userDrop->user || empty($userDrop->user->server_id)) {
                return;
            }

            $product = null;
            $viewPath = Yii::getAlias('@frontend/views/store') . '/_product.php';
            if (file_exists($viewPath)) {
                try {
                    $product = Yii::$app->view->renderFile($viewPath, [
                        'drop' => $userDrop->drop[0],
                        'serverId' => $userDrop->user->server_id,
                        'userDrop' => $userDrop,
                    ]);
                } catch (\Throwable $e) {
                    // без product ChatServer сделает fallback через commandBuyDrop
                }
            }

            $cacheKey = 'ws_buy_drop_' . $userDrop->user_id . '_' . $userDrop->id;
            $data = [
                'action' => 'buyDrop',
                'code' => 200,
                'id' => $userDrop->id,
                'timestamp' => time(),
            ];
            if ($product !== null) {
                $data['product'] = $product;
            }
            Yii::$app->cache->set($cacheKey, $data, 30);

            $listKey = 'ws_drops_list_' . $userDrop->user_id;
            $dropsList = Yii::$app->cache->get($listKey) ?: [];
            if (!in_array($userDrop->id, $dropsList)) {
                $dropsList[] = $userDrop->id;
                Yii::$app->cache->set($listKey, $dropsList, 60);
            }
        } catch (\Exception $ex) {
            if (method_exists(Yii::$app, 'telegramChats')) {
                Yii::$app->telegramChats->sendMessage('BuyDropJob ERROR: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
            }
        }
    }
}