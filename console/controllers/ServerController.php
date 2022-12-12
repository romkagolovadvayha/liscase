<?php
namespace console\controllers;

use consik\yii2websocket\WebSocketServer;
use console\daemons\Battle;
use Ratchet\App;
use yii\console\Controller;

class ServerController extends Controller
{
    public function actionStart() {
        $app = new App('liscase.local', 8080);
        $app->route('/battleonline', new Battle(), ['*']);
        $app->run();
    }
}