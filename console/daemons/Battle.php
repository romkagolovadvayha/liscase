<?php
namespace console\daemons;

use common\models\user\User;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use yii\helpers\Console;

class Battle implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $userInfo = new BattleClient();
        $userInfo->connection = $conn;
        $this->clients->attach($userInfo);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $userId = null;
        $request = json_decode($msg, true);
        if (isset($request['session_id'])) {
            // Определяем по JwtToken текущего пользователя
            try {
                $user = User::findByJwtToken($request['session_id']);
                if (!empty($user)) {
                    $userId = $user->id;
                }
            } catch (\Exception $ex) {
                Console::stdout("\nerror: " . $ex->getMessage());
            }
        }
        foreach ($this->clients as $client) {
            $client->userId = $userId;
            $client->connection->send(json_encode(['message' => $msg, 'userId' => $userId]));
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $client = $this->findConnection($conn);
        if ($client !== null) {
         $this->clients->detach($client);
      }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    public function findConnection( ConnectionInterface $conn ) {
        // Ищем пользователя, который написал
        foreach ($this->clients as $client) {
            if ($client->connection === $conn) {
                return $client;
            }
        }
        return null;
    }
}