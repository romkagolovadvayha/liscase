<?php

namespace backend\controllers;

use backend\forms\box\SetsForm;
use common\components\base\Model;
use common\components\helpers\Role;
use common\models\box\Sets;
use common\models\box\SetsSearch;
use yii\web\Response;
use Yii;

class SetsController extends \backend\components\CrudController
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

    protected function _getSearchClassName()
    {
        return SetsSearch::class;
    }

    protected function _getFormClassName()
    {
        return SetsForm::class;
    }

    /**
     * @param Model $formModel
     * @param string $view
     *
     * @return string|array|\yii\web\Response
     */
    protected function _saveForm($formModel, $view)
    {
        if ($formModel->load(Yii::$app->request->post())) {
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return \yii\bootstrap5\ActiveForm::validate($formModel);
            }
            if ($formModel->saveRecord()) {
                return $this->redirect($this->getIndexUrl());
            }
        }
        return $this->render($view, [
            'model' => $formModel,
        ]);
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionDelete($id)
    {
        $formModel = Sets::findOne($id);
        if ($formModel !== null) {
            $formModel->delete();
        }

        $this->_setSearchModel();
        $this->_rememberIndexUrl();
        return $this->_renderIndex($this->_getSearchDataProvider());
    }
}