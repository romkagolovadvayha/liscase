<?php

namespace frontend\controllers;

use common\components\queue\stats\SaveRaidJob;
use common\components\queue\stats\SaveStatsJob;
use common\controllers\WebController;
use Yii;

class ApiStatsController extends WebController
{
    public $enableCsrfValidation = false;

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function actionUpdate($serverTag) {
        Yii::$app->queueStats->push(new SaveStatsJob([
            'data' => Yii::$app->request->getRawBody(),
            'serverTag' => $serverTag,
        ]));
    }

    public function actionRaid($serverTag) {
        Yii::$app->queueRaid->push(new SaveRaidJob([
            'data' => Yii::$app->request->getRawBody(),
            'serverTag' => $serverTag,
        ]));
        Yii::$app->telegramChats->sendMessage(Yii::$app->request->getRawBody());
        Yii::$app->telegramChats->sendMessage($serverTag);
    }
}
