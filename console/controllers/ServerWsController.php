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
        $this->killProcessListeningOnPort((int) $server->port);
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
        $this->killProcessListeningOnPort((int) $server->port);
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
        $this->killProcessListeningOnPort((int) $server->port);
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

    /**
     * Завершает процессы, которые слушают указанный TCP-порт (перед повторным запуском WS).
     * Windows: netstat + taskkill; Linux/macOS: lsof + kill.
     */
    private function killProcessListeningOnPort(int $port): void
    {
        if ($port <= 0 || $port > 65535) {
            return;
        }
        $pids = $this->findPidsListeningOnPort($port);
        foreach ($pids as $pid) {
            $pid = (int) $pid;
            if ($pid <= 4) {
                continue;
            }
            if (PHP_OS_FAMILY === 'Windows') {
                @exec('taskkill /F /PID ' . $pid . ' 2>NUL', $out, $code);
            } else {
                @exec('kill -9 ' . $pid . ' 2>/dev/null', $out, $code);
            }
        }
        if ($pids !== []) {
            $this->stdout(sprintf("Порт %d: остановлены процессы PID: %s\n", $port, implode(', ', $pids)));
            usleep(200000);
        }
    }

    /**
     * @return int[]
     */
    private function findPidsListeningOnPort(int $port): array
    {
        $pids = [];
        $port = (int) $port;
        if (PHP_OS_FAMILY === 'Windows') {
            @exec('netstat -ano', $lines, $code);
            if ($code !== 0 || empty($lines)) {
                return [];
            }
            $needle = ':' . $port;
            foreach ($lines as $line) {
                if (stripos($line, 'LISTENING') === false) {
                    continue;
                }
                if (strpos($line, $needle) === false) {
                    continue;
                }
                if (!preg_match('/:' . preg_quote((string) $port, '/') . '(?:\s|$)/', $line)) {
                    continue;
                }
                $parts = preg_split('/\s+/', trim($line));
                $pid = (int) end($parts);
                if ($pid > 4) {
                    $pids[] = $pid;
                }
            }
        } else {
            @exec('lsof -ti :' . $port . ' 2>/dev/null', $out, $code);
            if (!empty($out)) {
                foreach ($out as $line) {
                    $pid = (int) trim($line);
                    if ($pid > 4) {
                        $pids[] = $pid;
                    }
                }
            }
        }

        return array_values(array_unique($pids));
    }
}
