<?php

namespace backend\controllers;

use common\components\helpers\Role;
use Yii;
use common\models\news\News;
use common\models\news\NewsSearch;
use backend\components\CrudController;
use backend\forms\news\NewsForm;

class NewsController extends CrudController
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
        return NewsSearch::class;
    }

    protected function _getFormClassName()
    {
        return NewsForm::class;
    }

    public function actionPublish($id)
    {
        $model = News::findOne($id);

        if ($model->getContentModel('ru-RU')) {
            $model->status = News::STATUS_ACTIVE;
            $model->save(false);
        }

        return $this->redirect($this->getIndexUrl());
    }

    public function actionPrepare($id)
    {
        $model = News::findOne($id);

        $model->status = News::STATUS_PREPARE;
        $model->save(false);

        return $this->redirect($this->getIndexUrl());
    }

    public function actionDelete($id)
    {
        $model = News::findOne($id);

        $model->status = News::STATUS_DELETED;
        $model->save(false);

        return $this->redirect($this->getIndexUrl());
    }
}
