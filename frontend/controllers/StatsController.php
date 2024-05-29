<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\stats\Wipe;
use yii\web\NotFoundHttpException;
use Yii;

class StatsController extends WebController
{

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

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionIndex($server)
    {
        /** @var Servers $server */
        $server = Servers::find()
                ->cache(30)
                ->andWhere(['tag' => $server])
                ->one();

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }

        return $this->render('index', [
            'server'  => $server,
        ]);
    }

    public function actionSearch($q, $server) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Servers $server */
        $server = Servers::find()
                         ->cache(30)
                         ->andWhere(['tag' => $server])
                         ->one();

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }

        $stats = Wipe::getStatsOriginal($server);
        $result = [];
        $items = [];
        if (is_null($q)) {
            throw new NotFoundHttpException(Yii::t('common', 'Неверный запрос!'));
        }
        $q = mb_strtolower($q);
        foreach ($stats['models'] as $item) {
            if (strrpos(mb_strtolower($item['name']), $q) !== false || strrpos($item['steamid'], $q) !== false) {
                $items[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'server' => $server->tag,
                    'strtolower' => mb_strtolower($item['name']),
                    'steam_id' => $item['steamid'],
                ];
            }
        }
        $result['items'] = $items;
        return $result;
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionPlayer($server, $steamId)
    {
        /** @var Servers $server */
        $server = Servers::find()
                ->cache(30)
                ->andWhere(['tag' => $server])
                ->one();

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }

        return $this->render('player', [
            'server'  => $server,
            'steamId'  => $steamId,
        ]);
    }

    /**
     * @param $id
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {

    }

}
