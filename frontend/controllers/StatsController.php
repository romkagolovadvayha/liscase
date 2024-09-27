<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\stats\Wipe;
use common\models\user\User;
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
                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                         ->andWhere(['tag' => $server])
                         ->one();

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }

        $result = [];
        $items = [];
        if (is_null($q)) {
            throw new NotFoundHttpException(Yii::t('common', 'Неверный запрос!'));
        }
        /** @var User[] $users */
        $users = User::find()
                     ->andWhere(['OR', ['LIKE', 'username', '%'.$q.'%', false], ['LIKE', 'steam_id', '%'.$q.'%', false]])
                     ->all();
        foreach ($users as $user) {
            $items[] = [
                'id' => $user->id,
                'name' => $user->username,
                'server' => $server->tag,
                'strtolower' => mb_strtolower($user->username),
                'steam_id' => $user->steam_id,
            ];
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
                ->andWhere(['status' => Servers::STATUS_ACTIVE])
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

}
