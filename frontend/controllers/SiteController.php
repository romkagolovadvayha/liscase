<?php

namespace frontend\controllers;

use backend\models\blog\BlogSearch;
use common\controllers\WebController;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use frontend\forms\promocode\PromocodeForm;
use Yii;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SiteController extends WebController
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
     * @return string|Response
     */
    public function actionIndex()
    {
        return $this->redirect('posts');
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

    public function actionSitemap()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/xml');
        $categories = BlogCategory::find()->andWhere(['status' => BlogCategory::STATUS_ACTIVE])->orderBy(['created_at' => SORT_ASC])->all();
        $articles = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->orderBy(['created_at' => SORT_ASC])->all();
        $users = User::find()->andWhere(['status' => User::STATUS_ACTIVE])->orderBy(['created_at' => SORT_ASC])->all();
        return $this->renderPartial('sitemap', [
            'articles' => $articles,
            'categories' => $categories,
            'users' => $users,
        ]);
    }

    public function actionRss($category = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $params = [];
        if (empty($category)) {
            $articles = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->orderBy(['created_at' => SORT_DESC])->all();
        } else {
            /** @var BlogCategory $category */
            $category = BlogCategory::findOne($category);
            if (empty($category) || !$category->status) {
                throw new NotFoundHttpException(Yii::t('common', 'Страница не найдена!'));
            }
            $category_ids = [$category->id];
            if (empty($category->parentCategory)) {
                $category_ids = array_keys($category->getChildsCategories($category->id));
            }
            $articles = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->andWhere(['IN', 'blog_category_id', $category_ids])->orderBy(['created_at' => SORT_DESC])->all();
            $params['category'] = $category;
        }
        $params['articles'] = $articles;
        return $this->renderPartial('rss', $params);
    }

    public function actionRobots()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/plain');
        return $this->renderPartial('robots');
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
