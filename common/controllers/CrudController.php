<?php

namespace common\controllers;

use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\helpers\Url;
use kartik\form\ActiveForm;
use common\components\base\Model;

abstract class CrudController extends WebController
{
    protected $_searchModel;
    protected $_indexUrl;

    public function actionIndex()
    {
        $this->_setSearchModel();

        $this->_rememberIndexUrl();

        return $this->_renderIndex($this->_getSearchDataProvider());
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function _setSearchModel()
    {
        $className = $this->_getSearchClassName();
        if (empty($className)) {
            throw new NotFoundHttpException(Yii::t('common', 'Метод не поддерживается'));
        }

        $this->_searchModel = new $className();
    }

    protected function _rememberIndexUrl($url = null)
    {
        if (!Yii::$app->request->isAjax) {
            Url::remember(Yii::$app->request->url, $this->getUniqueId());
        }
    }

    public function getIndexUrl()
    {
        $redirectUrl = Url::previous($this->getUniqueId());
        if (!empty($this->_indexUrl)) {
            $redirectUrl = $this->_indexUrl;
        }
        if (empty($redirectUrl)) {
            $redirectUrl = ['index'];
        }

        return $redirectUrl;
    }

    /**
     * @param ActiveDataProvider $dataProvider
     *
     * @return string
     */
    protected function _renderIndex($dataProvider)
    {
        return $this->render('index', [
            'searchModel'  => $this->_searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @return ActiveDataProvider
     */
    protected function _getSearchDataProvider()
    {
        return $this->_searchModel->search(Yii::$app->request->queryParams);
    }

    abstract protected function _getSearchClassName();

    public function actionCreate()
    {
        $formModel = $this->_getFormModel();

        return $this->_saveForm($formModel, 'create');
    }

    /**
     * @param int|null $id
     *
     * @return Model
     * @throws NotFoundHttpException
     */
    protected function _getFormModel($id = null)
    {
        $this->layout = $this->_getFormLayout();

        $className = $this->_getFormClassName();
        if (empty($className)) {
            throw new NotFoundHttpException(Yii::t('common', 'Метод не поддерживается'));
        }

        if ($id) {
            $formModel = $className::findOne($id);
        } else {
            $formModel = new $className();
        }

        if (method_exists($formModel, 'afterLoad')) {
            $formModel->afterLoad();
        }

        return $formModel;
    }

    /**
     * @param Model  $formModel
     * @param string $view
     *
     * @return string|array|\yii\web\Response
     */
    protected function _saveForm($formModel, $view)
    {
        if ($formModel->load(Yii::$app->request->post())) {
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;

                return ActiveForm::validate($formModel);
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
     * @return string
     */
    protected function _getFormLayout()
    {
        return '@app/views/layouts/main';
    }

    /**
     * @return string|null
     */
    protected function _getFormClassName()
    {
        return null;
    }

//    /**
//     * @return string
//     */
//    protected function _getFormScenario()
//    {
//        return Model::SCENARIO_DEFAULT;
//    }

    public function actionUpdate($id)
    {
        $formModel = $this->_getFormModel($id);

        return $this->_saveForm($formModel, 'update');
    }
}