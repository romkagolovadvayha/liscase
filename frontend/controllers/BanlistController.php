<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\stats\Teams;
use common\models\stats\Wipe;
use frontend\models\banlist\BansSearch;
use yii\base\BaseObject;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use Yii;

class BanlistController extends WebController
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
        $this->view->title = Yii::t('common', 'Бан лист');
        $this->view->params['page'] = 'bans';

        $searchModel = new BansSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $avatar = function (BansSearch $model) {
            return Html::img($model->user->getAvatar(), ['class' => 'w-24 h-24 min-w-24 min-h-24 rounded-6 object-cover']);
        };
        $steamId = function (BansSearch $model) {
            return $model->username;
        };
        $serverId = function (BansSearch $model) {
            if (empty($model->server_id)) {
                return Yii::t('common', 'Все сервера');
            }
            return $model->server->monitoring_name;
        };
        $bannedAt = function (BansSearch $model) {
            if (empty($model->banned_at)) {
                return Yii::t('common', 'Никогда');
            }
            return date('d.m.Y H:i:s', strtotime($model->banned_at));
        };
        $unbannedAt = function (BansSearch $model) {
            if (empty($model->unbanned_at)) {
                return Yii::t('common', 'Никогда');
            }
            return date('d.m.Y H:i:s', strtotime($model->unbanned_at));
        };

        $projectName = Yii::$app->settings->get('site_project_name');
        // Уникальный description с числом записей
        $desc = Yii::t('common',
                       'Общий бан-лист серверов {projectName}: {count} банов. Проверяйте причину бана, сервер и сроки. Список обновляется автоматически.',
                       ['count' => (int)$dataProvider->getTotalCount(), 'projectName' => $projectName]
        );

        $this->view->registerMetaTag([
                                         'name'    => 'description',
                                         'content' => $desc,
                                     ], 'description');

        // Заголовки для шаринга
        $this->view->registerMetaTag(['property' => 'og:title', 'content' => $this->view->title], 'og:title');
        $this->view->registerMetaTag(['property' => 'og:description', 'content' => $desc], 'og:description');

        // Канонический URL (если страница доступна по /banlist)
        $this->view->registerLinkTag([
                                         'rel'  => 'canonical',
                                         'href' => Yii::$app->params['homePage'] . '/banlist',
                                     ]);


        return $this->render('banlist.twig', [
            'SERVERS' => $servers,
            'PROJECT_STATS' => $projectStats,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'func' => [
                'avatar' => $avatar,
                'steam_id' => $steamId,
                'server_id' => $serverId,
                'unbanned_at' => $unbannedAt,
                'banned_at' => $bannedAt,
            ],
        ]);
    }

}
