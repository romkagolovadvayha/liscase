<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use frontend\forms\promocode\PromocodeForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Response;
use backend\models\blog\BlogSearch;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;
use yii\web\NotFoundHttpException;

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
        $promocodeForm = new PromocodeForm();
        if ($promocodeForm->load(Yii::$app->request->post())) {
            $model = $promocodeForm->saveRecord();
            if (!empty($model)) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Баланс пополнен на {PARAMS_PROMSUM} RUB', [
                    'PARAMS_PROMSUM' => $model->amount
                ]));
            } else {
                Yii::$app->session->addFlash('danger', array_values($promocodeForm->getFirstErrors())[0]);
            }
        }
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
        //$this->_botOpenBox();
        $this->layout = 'service';
        $result = [];
        $userDrops = UserDrop::getUsersDropLast();

        /** @var \common\models\skindrops\Skindrops[] $skindrops */
        $skindrops = \common\models\skindrops\Skindrops::find()
                                                       ->limit(10)
                                                       ->cache(30)
                                                       ->orderBy(['id' => SORT_DESC])
                                                       ->all();

        foreach ($userDrops as $userDrop) {
            foreach ($userDrop->drop as $index => $drop) {
                $item = [
                    'id' => $userDrop->id,
                    'image' => $drop->imageOrig->getImagePubUrl(),
                    'name' => Yii::t('database', $drop->name),
                    'bgImage' => $userDrop->box->imageOrig->getImagePubUrl(),
                    'bgName' => Yii::t('database', $userDrop->box->name),
                    'count' => "x" . $userDrop->count,
                    'userAvatar' => $userDrop->user->userProfile->avatar,
                    'userName' => $userDrop->user->userProfile->name,
                    'type' => 0,
                    'created_at' => $userDrop->created_at,
                ];
                $result[] = [
                    'id' =>  $userDrop->id,
                    'created_at' => $userDrop->created_at,
                    'view' =>  $this->render('@frontend/views/widgets/_last_drops_item', [
                        'item' => $item
                    ])
                ];
            }
        }

        foreach ($skindrops as $item) {
            /** @var \common\models\user\Auth $userAuth */
            $userAuth = \common\models\user\Auth::find()
                                                ->andWhere(['source_id' => $item->steam_id])
                                                ->one();
            $userAvatar = null;
            if (!empty($userAuth)) {
                $userAvatar = $userAuth->user->userProfile->avatar;
            }
            $data = [
                'id' => $item->id,
                'image' => $item->image,
                'name' => $item->name,
                'bgImage' => "/images/skindrops/skindrops.png",
                'bgName' => "SkinDrops",
                'count' => $item->price . " RUB",
                'userAvatar' => $userAvatar,
                'userName' => $item->name,
                'type' => 1,
                'created_at' => $item->created_at,
            ];
            $result[] = [
                'id' =>  $item->id,
                'created_at' => $item->created_at,
                'view' =>  $this->render('@frontend/views/widgets/_last_drops_item', [
                    'item' => $data
                ])
            ];
        }

        usort($result, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        $result = array_slice($result, 0, 5);

        header("Content-Type: application/json");
        return json_encode($result);
    }

    public function actionOnlineCounter()
    {
        $this->layout = 'service';
        return $this->render('@frontend/views/widgets/_online_counter');
    }

    public function actionPrivacy()
    {
        return $this->render('privacy');
    }

    public function actionAgreement()
    {
        return $this->render('agreement');
    }

    public function actionSitemap()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/xml');
        $categories = BlogCategory::find()->andWhere(['status' => BlogCategory::STATUS_ACTIVE])->orderBy(['created_at' => SORT_ASC])->all();
        $articles = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->orderBy(['created_at' => SORT_ASC])->all();
        $servers = Servers::find()->all();
        return $this->renderPartial('sitemap', [
            'articles' => $articles,
            'categories' => $categories,
            'servers' => $servers,
        ]);
    }

    public function actionRobots()
    {
        Yii::$app->response->headers->set('Content-Type','application/txt; charset=UTF-8');
        return $this->renderPartial('robots');
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
}
