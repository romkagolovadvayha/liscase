<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\statistics\Kills;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use yii\base\BaseObject;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

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
        $request = json_decode(Yii::$app->request->getRawBody(), 1);
        /** @var Servers $server */
        $server = Servers::find()
            ->andWhere(['tag' => $serverTag])
            ->one();
        if (empty($server)) {
            return;
        }
        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
        foreach ($request['users'] as $steamId => $params) {
            $statistics = Statistics::find()
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['server_tag' => $serverTag])
                ->andWhere(['wipe' => $wipeDate])
                ->indexBy('key')
                ->all();
            foreach ($params as $key => $value) {
                if (!empty($statistics[$key])) {
                    $statistics[$key]->value += $value;
                    $statistics[$key]->save();
                } else {
                    $model = new Statistics();
                    $model->steam_id = $steamId;
                    $model->server_tag = $serverTag;
                    $model->key = $key;
                    $model->value = $value;
                    $model->wipe = $wipeDate;
                    $model->save();
                }
            }
        }
        foreach ($request['kills'] as $item) {
            $model = new Kills();
            $model->steam_id = $item['steam_id'];
            $model->type = $item['type'];
            $model->dead = $item['dead'];
            $model->weapon = $item['weapon'];
            $model->distance = $item['distance'];
            $model->created_at = $item['date'];
            $model->server_tag = $serverTag;
            $model->wipe = $wipeDate;
            $model->save();
        }
        foreach ($request['teams'] as $item) {
            $model = new Teams();
            $model->steam_id = $item['steam_id'];
            $model->type = $item['type'];
            $model->team_author = $item['team_author'];
            $model->created_at = $item['created_at'];
            $model->server_tag = $serverTag;
            $model->wipe = $wipeDate;
            $model->save();
        }
        $server->players = $request['server']['online'] + 5;
        $server->joined = $request['server']['join'] + 3;
        $server->queued = $request['server']['queue'];
        $server->save();
    }
}
