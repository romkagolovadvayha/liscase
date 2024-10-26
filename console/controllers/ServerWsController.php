<?php
namespace console\controllers;

use yii\console\Controller;

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
}