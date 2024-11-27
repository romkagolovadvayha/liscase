<?php
namespace console\controllers;

use common\models\servers\Servers;
use yii\base\BaseObject;
use yii\console\Controller;
use WebSocket\Client;

class ServerWsController extends Controller
{
    /**
     * server-ws/start
     */
    public function actionStart($port = null)
    {
        $server = new ChatServer();
        if ($port) {
            $server->port = $port;
        }
        $server->start();
    }

    /**
     * server-ws/online
     */
    public function actionOnline()
    {
        Servers::notify();
    }

    /**
     * server-ws/test-client
     */
    public function actionTestClient($port = null)
    {
        $client = new Client(\Yii::$app->params['ws']);
        $client->send(json_encode([
            'action' => 'activatedDrop',
            'code' => 200,
            'message' => 'Товар успешно выдан!',
            'id' => 257513,
        ]));

    }
}