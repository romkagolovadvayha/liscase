<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

class ClansController extends WebController
{

    public function actionIndex()
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $item) {
            $server = $item;
            break;
        }

        if (!Yii::$app->user->isGuest) {
            $server = Yii::$app->user->identity->getCurrentServer();
        }

        Yii::$app->response->redirect('/clans/' . $server->tag, 301);
        Yii::$app->end();
    }

    public function actionClans($serverTag)
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

        $this->view->params['page'] = 'clans';

        return $this->render('clans.twig', [
            'SERVER'  => $server,
            'SERVERS'  => $servers,
            'SETTINGS' => Yii::$app->settings,
        ]);
    }
}
