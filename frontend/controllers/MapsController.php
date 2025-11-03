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
        // Register maps asset bundle for likes tooltip functionality
        \frontend\assets\MapsAsset::register($this->view);
        
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

        // уникальный description
        $desc = Yii::t('common',
                       'Голосование за карту на сервере {server}. Играть могут пользователи, проведшие на сервере 1+ часа. Диапазон размеров карты: {min}–{max}. Следующий вайп: {date} МСК.',
                       [
                           'server' => Yii::t('database', $server->name),
                           'min'    => (int)$server->min_map_size,
                           'max'    => (int)$server->max_map_size,
                           'date'   => Yii::$app->formatter->asDatetime($server->next_wipe, 'php:d.m.Y H:i'),
                       ]
        );

        // meta description
        $this->view->registerMetaTag([
                                         'name'    => 'description',
                                         'content' => $desc,
                                     ], 'description');

        // canonical (на текущий URL страницы голосования)
        $this->view->registerLinkTag([
                                         'rel'  => 'canonical',
                                         'href' => $server->getLink('maps'),
                                     ]);

        // (опционально) og:title/og:description для шаринга
        $this->view->registerMetaTag(['property' => 'og:title', 'content' => $this->view->title], 'og:title');
        $this->view->registerMetaTag(['property' => 'og:description', 'content' => $desc], 'og:description');


        $canonical = Yii::$app->params['homePage'] . '/maps';
        $this->view->registerLinkTag(['rel' => 'canonical', 'href' => $canonical]);

        $searchModel = new MapsSearch();
        $dataProvider = $searchModel->search($this->request->queryParams, $server->min_map_size, $server->max_map_size, $server->id);
        
        // Calculate max votes for progress bars
        $maxVotes = 0;
        foreach ($dataProvider->models as $map) {
            if ($map->votes > $maxVotes) {
                $maxVotes = $map->votes;
            }
        }
        
        return $this->render('maps.twig', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'SERVER' => $server,
            'SERVERS' => $servers,
            'maxVotes' => $maxVotes,
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

    /**
     * Get list of users who liked the map
     * @param int $id Map ID
     * @return array
     */
    public function actionGetLikes($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $map = Map::findOne($id);
        if (!$map) {
            return ['users' => [], 'total' => 0];
        }
        
        // Get total count
        $totalCount = UserMap::find()
            ->where(['map_id' => $id, 'vote' => 1])
            ->count();
        
        // Get only 5 latest
        $likes = UserMap::find()
            ->where(['map_id' => $id, 'vote' => 1])
            ->with(['user'])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(5)
            ->all();
        
        $users = [];
        foreach ($likes as $like) {
            if ($like->user) {
                $users[] = [
                    'username' => $like->user->username,
                    'avatar' => $like->user->getAvatar(),
                ];
            }
        }
        
        return [
            'users' => $users,
            'total' => (int)$totalCount,
        ];
    }
}