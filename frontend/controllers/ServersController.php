<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\stats\Teams;
use common\models\stats\Wipe;
use yii\web\NotFoundHttpException;
use Yii;

class ServersController extends WebController
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
    public function actionIndex()
    {
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $projectStats = \common\models\statistics\Statistics::projectStats();
        $this->view->title = Yii::t('common', 'Наши сервера Rust');
        $this->view->params['page'] = 'servers';
        $this->view->params['meta_description'] = Yii::t('common', "Список всех наших серверов Rust с подробным описанием, датами вайпов и IP-адресами. Узнайте, когда следующий вайп, подключитесь к любимому серверу и начните играть уже сегодня!");

        return $this->render('servers-list.twig', [
            'SERVERS' => $servers,
            'PROJECT_STATS' => $projectStats,
            'SETTINGS' => Yii::$app->settings
        ]);
    }

    public function actionWipeInfo($serverTag)
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
        return $this->renderAjax('wipe-info.twig', [
            'SERVERS' => $servers,
            'SERVER' => $server,
            'SETTINGS' => Yii::$app->settings
        ]);
    }

    /**
     * @param $serverTag
     *
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionRules($serverTag)
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
        $commands = json_decode($server->commands, 1);
        $this->view->title = Yii::t('common', 'Правила сервера') . " " . Yii::t('database', $server->name);
        $this->view->params['page'] = 'rules';

        return $this->render('rules.twig', [
            'SERVER' => $server,
            'SERVERS' => $servers,
            'COMMANDS' => $commands,
        ]);
    }

    public function actionWipeBlock()
    {
        $this->layout = 'service';
        return $this->renderAjax('wipe-block');
    }

}
