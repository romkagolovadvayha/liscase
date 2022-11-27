<?php

namespace backend\controllers;

use common\components\helpers\Role;
use Yii;
use yii\web\NotFoundHttpException;
use common\models\news\News;
use common\models\news\NewsContentSearch;
use backend\components\CrudController;
use backend\forms\news\NewsContentForm;

class NewsContentController extends CrudController
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return string
     */
    protected function _getFormLayout()
    {
        return 'main';
    }

    protected function _getSearchClassName()
    {
        return NewsContentSearch::class;
    }

    protected function _getFormClassName()
    {
        return NewsContentForm::class;
    }

    protected function _getSearchDataProvider()
    {
        return $this->_searchModel->search(Yii::$app->request->queryParams, function ($query) {
            $query->andWhere(['news_id' => $this->getNews()->id]);
        });
    }

    public function getNews()
    {
        $newsId = Yii::$app->request->get('newsId');

        $model = News::findOne($newsId);
        if (empty($model)) {
            throw new NotFoundHttpException('News not found');
        }

        return $model;
    }

    public function prepareUrl($action, $params = [])
    {
        $params = array_merge($params, [
            'newsId' => $this->getNews()->id,
        ]);

        $params = http_build_query($params);

        return '/news-content/' . $action . '?'. $params;
    }
}
