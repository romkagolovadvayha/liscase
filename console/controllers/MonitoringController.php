<?php

namespace console\controllers;

use common\models\servers\Servers;
use common\models\stats\Info;
use romkagolovadva\SourceQuery\SourceQuery;
use yii\console\Controller;
use yii\console\Exception;

class MonitoringController extends Controller
{
    /**
     * Получает информацию о серверах
     * monitoring/get-servers
     *
     * @throws \Exception
     */
    public function actionGetServers()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()->andWhere('db_host IS NOT NULL')->all();
        foreach ($servers as $server) {
            $model = Info::getInfo($server);
            $fakePlayers = 0;
            $fakeJoined = 0;
            if (in_array($server->tag, ['nolimit', 'max3'])) {
                $fakePlayers = 5;
                $fakeJoined = 5;
            }
            $server->players = $model->players + $fakePlayers;
            $server->joined = $model->joined + $fakeJoined;
            $server->queued = $model->queued;
            if (strtotime($model->updated_at) > time() - 60 * 8) {
                $server->status = Servers::STATUS_ACTIVE;
            } else {
                $server->status = Servers::STATUS_NOACTIVE;
            }
            $server->save();
        }
    }
}
