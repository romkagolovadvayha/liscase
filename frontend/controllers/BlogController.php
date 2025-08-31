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

        // на всякий случай фиксируем pageSize
        $dataProvider->pagination->pageSize = 10;

        if (!empty($searchModel->name)) {
            // лёгкий totalCount без join'ов (ускоряет pager)
            $dataProvider->setTotalCount(
                \common\models\blog\Blog::find()->alias('b')->where(
                        ['b.status' => \common\models\blog\Blog::STATUS_ACTIVE]
                    )->andFilterWhere(['like', 'b.name', $searchModel->name])->count()
            );
        }
        $categories = \common\models\blog\BlogCategory::find()
                                                      ->alias('bc')
                                                      ->where(['bc.status' => \common\models\blog\BlogCategory::STATUS_ACTIVE, 'bc.blog_category_id' => null])
                                                      ->with(['children'])                   // EAGER — без N+1
                                                      ->orderBy(['created_at' => SORT_DESC])
                                                      ->cache(60)
                                                      ->all();

        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
            'categories' => $categories,
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
