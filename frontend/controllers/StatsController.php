<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\statistics\Reports;
use common\models\stats\Wipe;
use common\models\user\User;
use common\models\user\UserTop;
use yii\helpers\Url;
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

    public function actionIndex($server)
    {
        Yii::$app->response->redirect('/servers/' . $server, 301);
        Yii::$app->end();
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionStats($serverTag)
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

        $this->view->title                      = Yii::t('common', 'Статистика сервера') . ' ' . Yii::t('database', $server->name);
        $this->view->params['meta_description'] = Yii::t('common', "Статистика игроков Rust.");
        $this->view->params['meta_keywords']    = Yii::t('common', "стастистика игроков, статистика сервера, статистика rust");
        $this->view->params['page'] = 'stats';

        $user = Yii::$app->user->identity;

        $items = UserTop::getUserTops($server, $server->currentWipe());
        if (!Yii::$app->user->isGuest) {
            $allUserTops = UserTop::getAllUserTops($server, $server->currentWipe());
        }
        $searchJS = User::searchJS();

        return $this->render('statistics.twig', [
            'SERVER'  => $server,
            'SERVERS'  => $servers,
            'USER'  => $user,
            'ITEMS'  => $items,
            'SEARCH_JS'  => $searchJS,
            'ALL_USER_TOP'  => $allUserTops,
        ]);
    }

    public function actionSearch($q, $serverId) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $result = [];
        if (is_null($q)) {
            throw new NotFoundHttpException(Yii::t('common', 'Неверный запрос!'));
        }

        $users = User::getUsers($serverId);
        $needle = mb_strtolower($q);

        function findKey($arr, $needle) {
            return array_values(
                array_filter(
                    $arr,
                    function ($element) use ($needle) {
                        return strpos($element['strtolower'], $needle) !== false || $element['steam_id'] == $needle;
                    }
                )
            );
        }

        $items = array_slice(findKey($users, $needle), 0, 15);
        $result['items'] = $items;
        return $result;
    }

    public function actionPlayer($server, $steamId)
    {
        Yii::$app->response->redirect("/servers/{$server}/{$steamId}", 301);
        Yii::$app->end();
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionPlayerNew($serverTag, $steamId)
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
