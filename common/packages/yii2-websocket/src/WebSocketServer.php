<?php

declare(strict_types=1);

namespace consik\yii2websocket;

use consik\yii2websocket\events\ExceptionEvent;
use consik\yii2websocket\events\WSClientCommandEvent;
use consik\yii2websocket\events\WSClientErrorEvent;
use consik\yii2websocket\events\WSClientEvent;
use consik\yii2websocket\events\WSClientMessageEvent;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Throwable;
use yii\base\Component;

class WebSocketServer extends Component implements MessageComponentInterface
{
    public const EVENT_WEBSOCKET_OPEN = 'ws_open';
    public const EVENT_WEBSOCKET_CLOSE = 'ws_close';
    public const EVENT_WEBSOCKET_OPEN_ERROR = 'ws_open_error';
    public const EVENT_CLIENT_CONNECTED = 'ws_client_connected';
    public const EVENT_CLIENT_ERROR = 'ws_client_error';
    public const EVENT_CLIENT_DISCONNECTED = 'ws_client_disconnected';
    public const EVENT_CLIENT_MESSAGE = 'ws_client_message';
    public const EVENT_CLIENT_RUN_COMMAND = 'ws_client_run_command';
    public const EVENT_CLIENT_END_COMMAND = 'ws_client_end_command';

    public $port = 8080;
    /**
     * Internal listeners are proxied by nginx and must not be reachable directly.
     */
    public $host = '127.0.0.1';
    protected $closeConnectionOnError = true;
    protected $runClientCommands = true;
    protected $server = null;
    protected $clients = null;

    public function start()
    {
        try {
            $this->server = IoServer::factory(
                new HttpServer(new WsServer($this)),
                $this->port,
                $this->host
            );
            $this->clients = new \SplObjectStorage();
            $this->trigger(self::EVENT_WEBSOCKET_OPEN);
            $this->server->run();

            return true;
        } catch (Throwable $exception) {
            $this->trigger(self::EVENT_WEBSOCKET_OPEN_ERROR, new ExceptionEvent([
                'exception' => $exception,
            ]));

            return false;
        }
    }

    public function stop()
    {
        if ($this->server !== null) {
            $this->server->socket->shutdown();
        }
        $this->trigger(self::EVENT_WEBSOCKET_CLOSE);
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients ??= new \SplObjectStorage();
        $this->trigger(self::EVENT_CLIENT_CONNECTED, new WSClientEvent(['client' => $conn]));
        $this->clients->attach($conn);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->trigger(self::EVENT_CLIENT_DISCONNECTED, new WSClientEvent(['client' => $conn]));
        $this->clients?->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->trigger(self::EVENT_CLIENT_ERROR, new WSClientErrorEvent([
            'client' => $conn,
            'exception' => $e,
        ]));

        if ($this->closeConnectionOnError) {
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->trigger(self::EVENT_CLIENT_MESSAGE, new WSClientMessageEvent([
            'client' => $from,
            'message' => (string) $msg,
        ]));

        if (!$this->runClientCommands) {
            return;
        }

        $command = $this->getCommand($from, $msg);
        $handler = $command === null ? null : 'command' . ucfirst($command);
        if ($handler === null || !method_exists($this, $handler)) {
            return;
        }

        $this->trigger(self::EVENT_CLIENT_RUN_COMMAND, new WSClientCommandEvent([
            'client' => $from,
            'command' => $command,
        ]));
        $result = $this->$handler($from, $msg);
        $this->trigger(self::EVENT_CLIENT_END_COMMAND, new WSClientCommandEvent([
            'client' => $from,
            'command' => $command,
            'result' => $result,
        ]));
    }

    protected function getCommand(ConnectionInterface $from, $msg)
    {
        return null;
    }
}
