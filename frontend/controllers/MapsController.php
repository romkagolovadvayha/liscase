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
        // Редирект 301 со старой версии на новую
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->andWhere(['secret_map' => 0])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        // Определяем целевой URL для редиректа
        if (empty($serverTag) && !Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
            if (!empty($user->server)) {
                $redirectUrl = '/maps-v2/' . $user->server->tag;
            } else {
                $redirectUrl = '/maps-v2/' . $servers[0]->tag;
            }
        } elseif (!empty($serverTag)) {
            $redirectUrl = '/maps-v2/' . $serverTag;
        } else {
            $redirectUrl = '/maps-v2';
        }

        // Выполняем редирект 301 на новую версию
        return $this->redirect($redirectUrl, 301);
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