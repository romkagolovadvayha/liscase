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
            ->andWhere(['link_name' => $blogLinkName])
            ->andWhere(['status' => Blog::STATUS_ACTIVE])
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
        return $this->render('category', [
            'blogCategory' => $blogCategory,
            'dataProvider' => $dataProvider
        ]);
    }

    /**
     * Displays homepage.
     *
     * @return string|Response
     */
    public function actionIndex()
    {
        $dataProvider = $this->_getDataProvider(new BlogSearch());

        return $this->render('index', [
            'dataProvider' => $dataProvider
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
            $query->andWhere(['status' => BlogSearch::STATUS_ACTIVE]);
        });
    }
}
