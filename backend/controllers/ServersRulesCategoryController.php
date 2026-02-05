<?php

namespace backend\controllers;

use backend\components\CrudController;
use backend\models\ServersRulesCategorySearch;
use common\components\helpers\Role;
use common\models\servers\ServersRulesCategory;
use yii\filters\VerbFilter;
use Yii;

/**
 * ServersRulesCategoryController implements the CRUD actions for ServersRulesCategory model.
 */
class ServersRulesCategoryController extends CrudController
{
    /**
     * @return string
     */
    protected function _getSearchClassName()
    {
        return ServersRulesCategorySearch::class;
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return ServersRulesCategory::class;
    }

    /**
     * @return string
     */
    protected function _getFormLayout()
    {
        return '@backend/views/layouts/main';
    }

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => \yii\filters\AccessControl::class,
                    'rules' => [
                        [
                            'allow' => true,
                            'roles' => [Role::ROLE_ADMIN],
                        ],
                    ],
                ],
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }
}

