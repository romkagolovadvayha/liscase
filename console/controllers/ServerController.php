<?php
namespace console\controllers;

use common\models\rcon\RconTasks;
use common\models\statistics\Statistics;
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

        foreach ($statistics as $model) {
            $model->user->is_gamer = 1;
            if ($model->user->save() && $group) {
                $command = "o.usergroup add \"{$model->user->steam_id}\" gamer";
                RconTasks::execute($command);
            } else {
                if (!empty($model->user->getErrors())) {
                    print_r($model->user->getErrors());
                    exit;
                }
            }
        }
    }
}