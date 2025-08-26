<?php

namespace frontend\controllers;

use common\components\queue\stats\SaveRaidJob;
use common\components\queue\stats\SaveSignsJob;
use common\components\queue\stats\SaveStatsJob;
use common\components\queue\stats\SaveTeamsJob;
use common\components\queue\stats\SkinsApprovedJob;
use common\components\queue\stats\UpdateUsersJob;
use common\controllers\WebController;
use Yii;

class SkinsController extends WebController
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

    public function actionApproved() {
        Yii::$app->queueTeam->push(new SkinsApprovedJob([
            'data' => Yii::$app->request->getRawBody(),
        ]));
    }

}
