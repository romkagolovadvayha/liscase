<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\clan\ClanSearch;
use common\models\clan\Clan;
use common\models\servers\Servers;
use common\models\user\UserTop;
use fedemotta\datatables\DataTablesAsset;
use frontend\forms\clans\ClanForm;
use frontend\models\banlist\BansSearch;
use yii\base\BaseObject;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

class ClansController extends WebController
{

    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

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
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $server = null;
        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }
        if (empty($server)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $this->view->params['page'] = 'clans';
        $this->view->params['clan_profile_block'] = true;

        $items = Clan::getClans($server);

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $items,
            'totalCount' => count($items),
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort'  => [
                'attributes' => ['title', 'kills', 'users_count', 'rocket_basic', 'c4thrown', 'scrap', 'sulfur.ore'],
                'defaultOrder' => ['kills' => SORT_DESC],
            ],
        ]);

        $avatar = function ($model) {
            return Html::img($model['logo'], ['class' => 'w-48 h-48 min-w-48 min-h-48 rounded-6 object-cover']);
        };
        $title = function ($model) {
            return "<div class=\"item_param_content\"><div class=\"item_param_content_title\">{$model['title']}</div><div class=\"item_param_content_description\">{$model['description_short']}</div></div>";
        };
        $kills = function ($model) {
            $str = number_format($model['kills'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $usersCount = function ($model) {
            $str = number_format($model['users_count'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $rocket = function ($model) {
            $str = number_format($model['rocket_basic'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $c4 = function ($model) {
            $str = number_format($model['c4thrown'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $scrap = function ($model) {
            $str = number_format($model['scrap'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $sulfur = function ($model) {
            $str = number_format($model['sulfur.ore'] ?? 0, 0, '.', ' ');
            return "<div class=\"item_param\"><span>{$str}</span></div>";
        };
        $clan = null;
        if (!Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
            $userClans = \common\models\clan\Clan::getUserClansList($server);
            if (!empty($userClans[$user->id])) {
                $clans = \common\models\clan\Clan::getClans($server);
                if (!empty($clans[$userClans[$user->id]])) {
                    $clan = $clans[$userClans[$user->id]];
                }
            }
        }

        $values = array_values($items);
        $goldClan = null;
        $silverClan = null;
        $bronzeClan = null;
        if (!empty($values[0])) {
            $goldClan = $values[0];
        }
        if (!empty($values[1])) {
            $silverClan = $values[1];
        }
        if (!empty($values[2])) {
            $bronzeClan = $values[2];
        }
        unset($values);

        return $this->render('clans.twig', [
            'SERVER'  => $server,
            'CLAN'  => $clan,
            'SERVERS'  => $servers,
            'GOLD_CLAN'  => $goldClan,
            'SILVER_CLAN'  => $silverClan,
            'BRONZE_CLAN'  => $bronzeClan,
            'SETTINGS' => Yii::$app->settings,
            'ITEMS' => $items,
            'DATA_PROVIDER' => $dataProvider,
            'func' => [
                'avatar' => $avatar,
                'title' => $title,
                'kills' => $kills,
                'users_count' => $usersCount,
                'rocket' => $rocket,
                'c4' => $c4,
                'scrap' => $scrap,
                'sulfur' => $sulfur,
            ],
        ]);
    }

    public function actionProfile($serverTag, $linkHash)
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Clan $model */
        $model = Clan::find()
            ->andWhere(['link_hash' => $linkHash])
            ->one();

        if (empty($model)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        $server = null;
        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }
        if (empty($server)) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $this->view->params['page'] = 'clans';

        $user = Yii::$app->user->identity;
        $roles = [];
        if (!empty($user)) {
            $roles = $user->getRolesClan($model->user_id, $model->id);
        }

        return $this->render('profile.twig', [
            'SERVER'  => $server,
            'SERVERS'  => $servers,
            'CLAN'  => $model,
            'ROLES'  => $roles,
            'SETTINGS' => Yii::$app->settings,
        ]);
    }

    public function actionCreate()
    {
        if (!Yii::$app->settings->get('section_clans')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не авторизованы!'));
            return $this->redirect(['index']);
        }

        $model = new ClanForm();
        if ($this->request->isPost) {
            $server = $user->getCurrentServer();
            if ($model->load($this->request->post()) && $model->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Клан успешно создан!'));
                return $this->redirect([$model->getLink('profile', $server->tag)]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->renderAjax('clan-form', [
            'model' => $model,
        ]);
    }
}
