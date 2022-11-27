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
}