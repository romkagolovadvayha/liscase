<?php

namespace backend\controllers;

use backend\forms\box\DropForm;
use common\components\base\Model;
use common\components\helpers\Role;
use common\models\box\Drop;
use common\models\box\DropSearch;
use yii\web\Response;
use Yii;

class DropController extends \backend\components\CrudController
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
        return DropSearch::class;
    }

    protected function _getFormClassName()
    {
        return DropForm::class;
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
        $formModel = Drop::findOne($id);
        if ($formModel !== null) {
            $formModel->delete();
        }

        $this->_setSearchModel();
        $this->_rememberIndexUrl();
        return $this->_renderIndex($this->_getSearchDataProvider());
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionSearch($term)
    {
        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->andFilterWhere(['LIKE', 'name', $term])
            ->all();

        $result = [];
        foreach ($drops as $item) {
            $result[$item->id] = $item->name;
        }
        echo json_encode($result);
    }
}