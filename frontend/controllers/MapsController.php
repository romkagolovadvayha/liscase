<?php
namespace frontend\controllers;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use frontend\models\maps\MapsSearch;
use Yii;
use common\models\map\Map;
use common\models\map\UserMap;
use yii\base\BaseObject;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class MapsController extends Controller
{
    public function actionIndex($serverTag = null)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->andWhere(['secret_map' => 0])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        if (empty($serverTag) && !Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
            if (!empty($user->server)) {
                return $this->redirect($user->server->getLink('maps'));
            } else {
                return $this->redirect($servers[0]->getLink('maps'));
            }
        }
        foreach ($servers as $item) {
            if ($item->tag === $serverTag) {
                $server = $item;
                break;
            }
        }

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }
        $this->view->title = Yii::t('common', 'Выбор карты на сервере') . " " . Yii::t('database', $server->name);
        $this->view->params['page'] = 'maps';

        $searchModel = new MapsSearch();
        $dataProvider = $searchModel->search($this->request->queryParams, $server->min_map_size, $server->max_map_size, $server->id);
        return $this->render('maps.twig', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'SERVER' => $server,
            'SERVERS' => $servers,
        ]);
    }

    public function actionVote($id = null)
    {
        $model = Map::findOne($id);
        if (empty($model)) {
            throw new NotFoundHttpException(Yii::t('common', 'Карта не найдена!'));
        }
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы голосовать, нужно авторизоваться на сайте!'));
            return $this->renderAjax('@frontend/views/maps/like', [
                'model' => $model,
                'liked' => false,
            ]);
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        $exist = UserMap::find()
            ->andWhere(['map_id' => $model->id])
            ->andWhere(['user_id' => $user->id])
            ->exists();

        if ($exist) {
            Yii::$app->session->addFlash('success', Yii::t('common', 'Ваш голос снят!'));
            $model->unvoted();
        } else {
            $playtime = Statistics::find()
                ->andWhere(['steam_id' => $user->steam_id])
                ->andWhere(['key' => 'playtime'])
                ->sum('value');

            if ($playtime < 60) {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Чтобы проголосовать, нужно отыграть на сервере минимум 1 час!'));
            } else {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Ваш голос успешно учтен!'));
                $model->voted();
            }
        }

        return $this->renderAjax('@frontend/views/maps/like', [
            'model' => $model,
            'liked' => !$exist,
        ]);
    }
}