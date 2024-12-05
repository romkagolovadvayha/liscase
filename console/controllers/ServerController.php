<?php
namespace console\controllers;

use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use consik\yii2websocket\WebSocketServer;
use console\daemons\Battle;
use Ratchet\App;
use yii\console\Controller;

class ServerController extends Controller
{
    /**
     * server/check-gamer
     */
    public function actionCheckGamer($group = true) {
        $limit = 50;
        if (!$group) {
            $limit = 1000;
        }

        /** @var Statistics[] $statistics */
        $statistics = Statistics::find()
            ->alias('s')
            ->joinWith(['user u'])
            ->andWhere(['u.is_gamer' => 0])
            ->andWhere(['s.key' => 'playtime'])
            ->andWhere(['>=', 's.value', 90])
            ->limit($limit)
            ->all();

        echo "count: " . count($statistics) . PHP_EOL;
        $count = 0;
        foreach ($statistics as $model) {
            $model->user->is_gamer = 1;
            if ($model->user->save(false)) {
                $count++;
                if ($group) {
                    $command = "o.usergroup add \"{$model->user->steam_id}\" gamer";
                    RconTasks::execute($command);
                }
                /** @var user[] $_users */
                $_users = User::find()
                              ->andWhere(['steam_id' => $model->user->steam_id])
                              ->all();
                if (count($_users) > 1) {
                    foreach ($_users as $_user) {
                        if ($model->user->id == $_user->id) {
                            continue;
                        }
                        $_user->is_gamer = 1;
                        $_user->save(false);
                    }
                }
            } else {
                if (!empty($model->user->getErrors())) {
                    print_r($model->user->getErrors());
                    exit;
                }
            }
        }
        echo "is_gamer: " . $count . PHP_EOL;
    }

    /**
     * server/check-status
     */
    public function actionCheckStatus() {
        /** @var Servers $server */
        $servers = Servers::find()
                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                         ->all();

        foreach ($servers as $server) {
            if (time() - strtotime($server->updated_at) > 65) {
                $server->status = Servers::STATUS_NOACTIVE;
                $server->save();
            }
        }
    }
}