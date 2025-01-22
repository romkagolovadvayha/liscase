<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\statistics\Reports;
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
    public function actionIndex($serverTag)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }

        $this->view->params['page'] = 'stats';

        return $this->render('index', [
            'server'  => $server,
            'servers'  => $servers,
        ]);
    }

    public function actionSearch($q, $server) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Servers $server */
        $server = Servers::find()
                         ->cache(30)
                         ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
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
        $date = new \DateTime();
        $date->modify('-30 day');
        /** @var User[] $users */
        $users = User::find()
                     ->andWhere(['OR', ['LIKE', 'username', '%'.$q.'%', false], ['LIKE', 'steam_id', '%'.$q.'%', false]])
//                     ->andWhere(['>=', 'last_visit_server_at', $date->format('Y-m-d H:i:s')])
                     ->andWhere(['status' => User::STATUS_ACTIVE])
                     ->andWhere(['is_stats' => true])
                     ->orderBy(['last_visit_server_at' => SORT_DESC])
                     ->all();
        foreach ($users as $user) {
            $items[] = [
                'id' => $user->id,
                'name' => $user->username,
                'server' => $server->tag,
                'strtolower' => mb_strtolower($user->username),
                'steam_id' => $user->steam_id,
                'statsLink' => $user->getLink('stats'),
                'avatar' => $user->getAvatar(),
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
    public function actionPlayer($serverTag, $steamId)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(60)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }


        /** @var User $_user */
        $_user = User::find()->andWhere(['steam_id' => $steamId])->one();

        $this->view->params['page'] = 'stats';
        $this->view->params['_user'] = $_user;
        $this->view->params['_server'] = $server;

        return $this->render('player', [
            'server'  => $server,
            'servers'  => $servers,
            'steamId'  => $steamId,
            'user'  => $_user,
        ]);
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionReport($serverTag, $steamId)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(60)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }


        /** @var User $user */
        $user = Yii::$app->user->identity;
        /** @var User $_user */
        $_user = User::find()->andWhere(['steam_id' => $steamId])->one();

        $this->view->params['page'] = 'stats';
        $this->view->params['_user'] = $_user;

        $reportForm = new \frontend\forms\user\ReportForm();
        $reportForm->setUser($user);
        $reportForm->setRecepientUser($_user);
        $reportForm->setServer($server);

        $exist = Reports::find()
                        ->andWhere(['steam_id' => $user->steam_id])
                        ->andWhere(['recepient_steam_id' => $_user->id])
                        ->andWhere(['wipe' => $server->currentWipe()])
                        ->exists();

        if ($reportForm->load(Yii::$app->request->post())) {
            if ($reportForm->saveRecord()) {
                Yii::$app->session->addFlash('success', 'Жалоба на игрока успешно отправлена!');
                $exist = true;
//                return $this->redirect($_user->getLink('stats'));
            }
        }

        return $this->renderAjax('reportFrom', [
            'server'  => $server,
            'servers'  => $servers,
            'steamId'  => $steamId,
            'user'  => $_user,
            'reportForm'  => $reportForm,
            'reportExist'  => $exist,
        ]);
    }

}
