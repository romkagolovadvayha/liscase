<?php
namespace console\controllers;

use Yii;
use common\models\servers\Servers;
use yii\base\BaseObject;
use yii\console\Controller;
use WebSocket\Client;
use console\controllers\NotificationServer;
use console\controllers\FrontendPushGatewayServer;

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
     * server-ws/start-notifications
     * Запуск нового WebSocket сервера для уведомлений
     */
    public function actionStartNotifications($port = null)
    {
        $server = new NotificationServer();
        if ($port) {
            $server->port = $port;
        }
        $server->start();
    }

    /**
     * server-ws/start-frontend-push
     * Push-шлюз для Next.js: единый формат событий (см. FrontendPushGatewayServer).
     * По умолчанию порт 8092 (проксируется nginx location /fp/).
     * Важно: Yii::$app->db (common/config/db-local.php) должен указывать на ту же БД, что и API,
     * иначе subscribeTicket для владельца не найдёт строку в support.
     */
    public function actionStartFrontendPush($port = null)
    {
        $server = new FrontendPushGatewayServer();
        if ($port) {
            $server->port = (int) $port;
        } else {
            $server->port = (int) (Yii::$app->params['frontendPushWsPort'] ?? 8092);
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

    /**
     * server-ws/test-rcon
     */
    public function actionTestRcon($port = null)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()->cache(30)->andWhere(['status' => Servers::STATUS_ACTIVE])->all();
        foreach ($servers as $server) {
            if (!empty($serversCommand) && !in_array($server->tag, $serversCommand)) {
                continue;
            }
            $response = (Yii::$app->curl)
                ->setHeaders(['Content-Type' => 'application/json'])
                ->setRawPostData(json_encode(['server' => $server->tag, 'command' => 'o.plugins']))
                ->post(Yii::$app->settings->get('site_rconUrl') . '/send');

            $response = json_decode($response, 1)['result'];
            echo $response;
            break;
        }
    }
}
