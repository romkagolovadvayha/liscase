<?php

namespace frontend\controllers;

use common\models\user\UserBox;
use common\models\user\UserDrop;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

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
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    private function _botOpenBox() {
        $cacheKey = 'botGenerate';
        if (Yii::$app->cache->get($cacheKey)) {
            return;
        }
        Yii::$app->cache->set($cacheKey, 1, 5);
        $rand = rand(1, 3);
        for ($i = 0; $i < $rand; $i++) {
            UserBox::botGenerate();
        }
    }

    public function actionLastDrops()
    {
        $this->_botOpenBox();
        $this->layout = 'service';
        $result = [];
        $userDrops = UserDrop::getUsersDropLast();
        foreach ($userDrops as $userDrop) {
            $result[] = [
                'id' =>  $userDrop->id,
                'view' =>  $this->render('@frontend/views/widgets/_last_drops_item', [
                                'userDrop' => $userDrop,
                                'opened' => true,
                           ])
            ];
        }
        header("Content-Type: application/json");
        return json_encode($result);
    }

    public function actionOnlineCounter()
    {
        $this->layout = 'service';
        return $this->render('@frontend/views/widgets/_online_counter');
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

}
