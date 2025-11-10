<?php

namespace frontend\controllers;

use backend\models\blog\BlogSearch;
use common\controllers\WebController;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use yii\base\BaseObject;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;
use DateTime;

class BlogController extends WebController
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
    public function actionView($categoryLinkName, $blogLinkName, $categoryLinkNameChild = null)
    {
        /** @var Blog $blog */
        $blog = Blog::find()
            ->alias('b')
            ->joinWith(['blogCategory', 'blogCategory.parentCategory', 'blogImages', 'blogRatings'])
            ->andWhere(['b.link_name' => $blogLinkName])
            ->andWhere(['b.status' => Blog::STATUS_ACTIVE])
            ->one();

        if (empty($blog) || !$blog->checkUrl($categoryLinkName, $blogLinkName, $categoryLinkNameChild)) {
            throw new NotFoundHttpException(Yii::t('common', 'Запись не существует!'));
        }

        return $this->render('view', [
            'blog' => $blog
        ]);
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionCategory($categoryLinkName, $categoryLinkNameChild = null)
    {
        $catName = $categoryLinkName;
        if (!empty($categoryLinkNameChild)) {
            $catName = $categoryLinkNameChild;
        }
        /** @var BlogCategory $blogCategory */
        $blogCategory = BlogCategory::find()
                    ->andWhere(['status' => BlogCategory::STATUS_ACTIVE])
                    ->andWhere(['link_name' => $catName])
                    ->one();
        if (empty($blogCategory) || !$blogCategory->checkUrl($categoryLinkName, $categoryLinkNameChild)) {
            throw new NotFoundHttpException(Yii::t('common', 'Категория не существует!'));
        }
        $searchModel = new BlogSearch();
        $searchModel->category_ids = [$blogCategory->id];
        if (empty($blogCategory->parentCategory)) {
            $searchModel->category_ids = array_keys($blogCategory->getChildsCategories($blogCategory->id));
        }
        $dataProvider = $this->_getDataProvider($searchModel);

        if (!empty($searchModel->name)) {
            // лёгкий totalCount без join'ов (ускоряет pager)
            $dataProvider->setTotalCount(
                \common\models\blog\Blog::find()->alias('b')->where(
                    ['b.status' => \common\models\blog\Blog::STATUS_ACTIVE]
                )->andFilterWhere(['like', 'b.name', $searchModel->name])->count()
            );
        }
        $request = Yii::$app->request;

        $pagination = $dataProvider->getPagination();
        $sort = $dataProvider->getSort();

        if ($pagination) {
            $pagination->route = 'blog/category';
            $paginationParams = $request->getQueryParams();
            $paginationParams['categoryLinkName'] = $categoryLinkName;
            if (!empty($categoryLinkNameChild)) {
                $paginationParams['categoryLinkNameChild'] = $categoryLinkNameChild;
            }
            $pagination->params = $paginationParams;
        }
        if ($sort) {
            $sort->route = 'blog/category';
            $sortParams = $request->getQueryParams();
            $sortParams['categoryLinkName'] = $categoryLinkName;
            if (!empty($categoryLinkNameChild)) {
                $sortParams['categoryLinkNameChild'] = $categoryLinkNameChild;
            }
            $sort->params = $sortParams;
        }

        $canonicalParams = $request->getQueryParams();
        unset($canonicalParams['categoryLinkName'], $canonicalParams['categoryLinkNameChild']);
        if ($pagination) {
            $pageParam = $pagination->pageParam;
            if (isset($canonicalParams[$pageParam]) && (int)$canonicalParams[$pageParam] <= 1) {
                unset($canonicalParams[$pageParam]);
            }
            $pageSizeParam = $pagination->pageSizeParam;
            if (!empty($pageSizeParam) && isset($canonicalParams[$pageSizeParam]) && (int)$canonicalParams[$pageSizeParam] === $pagination->pageSize) {
                unset($canonicalParams[$pageSizeParam]);
            }
        }
        if ($sort) {
            $sortParam = $sort->sortParam;
            if (!empty($canonicalParams[$sortParam])) {
                $defaultOrder = $sort->defaultOrder;
                $defaultSortValue = null;
                if (!empty($defaultOrder)) {
                    $attr = array_key_first($defaultOrder);
                    $direction = $defaultOrder[$attr];
                    $defaultSortValue = $direction === SORT_DESC ? '-' . $attr : $attr;
                }
                if ($defaultSortValue !== null && $canonicalParams[$sortParam] === $defaultSortValue) {
                    unset($canonicalParams[$sortParam]);
                }
            }
        }
        $canonicalQuery = $canonicalParams ? '?' . http_build_query($canonicalParams) : '';
        $canonicalUrl = Yii::$app->params['homePage'] . $blogCategory->getUrl() . $canonicalQuery;
        $this->view->registerLinkTag(['rel' => 'canonical', 'href' => $canonicalUrl]);
        $this->view->registerMetaTag(['name' => 'robots', 'content' => 'index,follow,max-image-preview:large']);

        $categories = \common\models\blog\BlogCategory::find()
                                                      ->alias('bc')
                                                      ->where(['bc.status' => \common\models\blog\BlogCategory::STATUS_ACTIVE, 'bc.blog_category_id' => null])
                                                      ->with(['children'])                   // EAGER — без N+1
                                                      ->orderBy(['created_at' => SORT_DESC])
                                                      ->cache(60)
                                                      ->all();

        // на всякий случай фиксируем pageSize
        $dataProvider->pagination->pageSize = 10;
        return $this->render('category', [
            'blogCategory' => $blogCategory,
            'dataProvider' => $dataProvider,
            'searchModel'  => $searchModel,
            'categories'  => $categories,
        ]);
    }

    /**
     * Displays homepage.
     *
     * @return string|Response
     */
    public function actionIndex()
    {
        $searchModel  = new \backend\models\blog\BlogSearch();
        $dataProvider = $this->_getDataProvider($searchModel);

        $dataProvider->pagination->pageSize = 10;

        if (!empty($searchModel->name)) {
            $dataProvider->setTotalCount(
                \common\models\blog\Blog::find()->alias('b')
                                        ->where(['b.status' => \common\models\blog\Blog::STATUS_ACTIVE])
                                        ->andFilterWhere(['like', 'b.name', $searchModel->name])
                                        ->count()
            );
        }

        $categories = \common\models\blog\BlogCategory::find()
                                                      ->alias('bc')
                                                      ->where(['bc.status' => \common\models\blog\BlogCategory::STATUS_ACTIVE, 'bc.blog_category_id' => null])
                                                      ->with(['children'])
                                                      ->orderBy(['created_at' => SORT_DESC])
                                                      ->cache(60)
                                                      ->all();

        // 🔽 Добавляем мета-description
        $request = Yii::$app->request;
        $canonicalParams = $request->getQueryParams();
        unset($canonicalParams['categoryLinkName'], $canonicalParams['categoryLinkNameChild']);
        if ($dataProvider->getPagination()) {
            $pageParam = $dataProvider->getPagination()->pageParam;
            if (isset($canonicalParams[$pageParam]) && (int)$canonicalParams[$pageParam] <= 1) {
                unset($canonicalParams[$pageParam]);
            }
            $pageSizeParam = $dataProvider->getPagination()->pageSizeParam;
            if (!empty($pageSizeParam) && isset($canonicalParams[$pageSizeParam]) && (int)$canonicalParams[$pageSizeParam] === $dataProvider->getPagination()->pageSize) {
                unset($canonicalParams[$pageSizeParam]);
            }
        }
        $canonicalQuery = $canonicalParams ? '?' . http_build_query($canonicalParams) : '';
        $canonicalIndexUrl = Yii::$app->params['homePage'] . '/posts' . $canonicalQuery;
        $this->view->registerLinkTag([
                                         'rel' => 'canonical',
                                         'href' => $canonicalIndexUrl,
                                     ]);

        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
            'categories'   => $categories,
        ]);
    }


    /**
     * @param BlogSearch $searchModel
     *
     * @return \yii\data\ActiveDataProvider
     */
    protected function _getDataProvider(BlogSearch $searchModel)
    {
        return $searchModel->search(Yii::$app->request->queryParams, function ($query) {
            $query->andWhere(['b.status' => BlogSearch::STATUS_ACTIVE]);
        });
    }
}
