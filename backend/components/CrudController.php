<?php

namespace backend\components;

use Yii;

abstract class CrudController extends \common\controllers\CrudController
{
    /**
     * @return string
     */
    protected function _getFormLayout()
    {
        return '@backend/views/layouts/form';
    }

    /**
     * Кнопки в шапке на странице списка (переопределить в дочернем классе).
     * @return array
     */
    protected function getIndexHeaderActions()
    {
        return [];
    }

    public function actionIndex()
    {
        $this->_setSearchModel();
        $this->_rememberIndexUrl();

        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $this->_searchModel;
        $this->view->params['headerActions'] = $this->getIndexHeaderActions();

        return $this->_renderIndex($this->_getSearchDataProvider());
    }
}