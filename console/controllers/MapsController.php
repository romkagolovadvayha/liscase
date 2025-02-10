<?php

namespace console\controllers;

use common\components\queue\process\MapGenerateJob;
use common\models\servers\Servers;
use yii\base\BaseObject;
use yii\console\Controller;

class MapsController extends Controller
{

    /**
     * maps/start
     */
    public function actionStart() {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $server) {
            \Yii::$app->queueProcess->push(
                new MapGenerateJob(
                    [
                        'serverId'  => $server->id,
                    ]
                )
            );
        }
    }


}
