<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use frontend\forms\promocode\PromocodeForm;
use Yii;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use backend\models\blog\BlogSearch;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;
use yii\web\NotFoundHttpException;
use common\components\web\Cookie;

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
        $this->view->params['page'] = 'home';
        $this->view->params['meta_description'] = Yii::$app->settings->get('site_description');
        $this->view->params['meta_keywords']    = Yii::$app->settings->get('site_keywords');
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

    public function actionPersonalinformation()
    {
        return $this->render('personalinformation');
    }

    public function actionSitemap()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/xml');
        $categories = BlogCategory::find()->andWhere(['status' => BlogCategory::STATUS_ACTIVE])->orderBy(['created_at' => SORT_ASC])->all();
        $articles = Blog::find()->andWhere(['status' => Blog::STATUS_ACTIVE])->orderBy(['created_at' => SORT_ASC])->all();
        $servers = Servers::find()
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();
        return $this->renderPartial('sitemap', [
            'articles' => $articles,
            'categories' => $categories,
            'servers' => $servers,
        ]);
    }

    public function actionMenuToggle()
    {
        header("Content-Type: application/json");
        $hide = Cookie::getValue('isMenuHide') == 'true';
        Cookie::add('isMenuHide', !$hide, true, 60*60*24*365*5);
        return json_encode(['success' => true]);
    }

    public function actionPromocode()
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
        return $this->renderAjax('promocode');
    }

    public function actionMenu($page = null)
    {
        $userData = \common\models\user\User::userData();
        return $this->renderAjax('@frontend/views/layouts/menu.twig', [
            'MENU_HIDDEN' => false,
            'MOBILE' => true,
            'USER' => $userData,
            'PAGE' => $page,
        ]);
    }

    public function actionRobots()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        $robotsTxt = Yii::$app->settings->get('site_robots');
        $robotsTxt = str_replace('{HOME_PAGE}', Yii::$app->params['homePage'], $robotsTxt);

        return $robotsTxt;
    }

    public function actionMute($steamId, $serverTag, $reason)
    {
        /** @var Servers $server */
        $server = Servers::find()
                         ->cache(30)
                         ->andWhere(['tag' => $serverTag])
                         ->andWhere(['status' => Servers::STATUS_ACTIVE])
                         ->one();

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }

        $command = "bcm.mute {$steamId} 1h \"{$reason}\"";
        $response = shell_exec("node /var/www/www-root/data/var/www/" . Yii::$app->settings->get('site_domain') . "/node/webrcon/src/send.js \"{$server->ip}\" {$server->rcon} \"{$server->rcon_password}\" \"{$command}\" 2>&1");
        print_r($response);
        exit;
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
