<?php

namespace frontend\controllers;

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
}
