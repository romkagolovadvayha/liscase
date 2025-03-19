<?php

namespace frontend\controllers;

use common\components\queue\stats\SaveRaidJob;
use common\components\queue\stats\SaveSignsJob;
use common\components\queue\stats\SaveStatsJob;
use common\components\queue\stats\SaveTeamsJob;
use common\components\queue\stats\UpdateUsersJob;
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

    public function actionUpdateUsers($serverTag) {
        Yii::$app->queueOnline->push(new UpdateUsersJob([
            'data' => Yii::$app->request->getRawBody(),
            'serverTag' => $serverTag,
        ]));
    }

    public function actionRaid($serverTag) {
        Yii::$app->queueRaid->push(new SaveRaidJob([
            'data' => Yii::$app->request->getRawBody(),
            'serverTag' => $serverTag,
        ]));
    }

    public function actionTeams() {
        Yii::$app->queueTeam->push(new SaveTeamsJob([
            'data' => Yii::$app->request->getRawBody(),
        ]));
    }

    public function actionSigns() {
        Yii::$app->queueProcess->push(new SaveSignsJob([
            'data' => Yii::$app->request->getRawBody(),
        ]));
    }
}
