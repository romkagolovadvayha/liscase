<?php

namespace common\components\queue\websocket;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;
use WebSocket\Client;

/**
 * Job для отправки WebSocket уведомлений об обновлении баланса
 */
class NotifyBalanceJob extends BaseObject implements JobInterface
{
    public $userId;
    public $balance;
    public $balanceStr;

    /**
     * @param \yii\queue\Queue $queue
     * @return void
     */
    public function execute($queue)
    {
        if (!$this->userId || !isset($this->balance)) {
            return;
        }

        try {
            // Создаём одно подключение для этого Job
            $client = new Client(Yii::$app->params['ws']);
            $client->send(
                json_encode([
                    'action'     => 'updatedBalance',
                    'code'       => 200,
                    'balanceStr' => $this->balanceStr,
                    'balance'    => $this->balance,
                    'user_id'    => $this->userId,
                ])
            );
            $client->close();
        } catch (\Exception $ex) {
            // Логируем ошибку, но не падаем
            Yii::warning('WebSocket notification failed: ' . $ex->getMessage(), 'websocket');
        }
    }
}

