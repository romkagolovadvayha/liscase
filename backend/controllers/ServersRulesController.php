<?php

namespace backend\controllers;

use backend\components\CrudController;
use backend\models\ServersRulesSearch;
use common\components\helpers\Role;
use common\models\servers\ServersRules;
use yii\filters\VerbFilter;
use Yii;

/**
 * ServersRulesController implements the CRUD actions for ServersRules model.
 */
class ServersRulesController extends CrudController
{
    /**
     * @return string
     */
    protected function _getSearchClassName()
    {
        return ServersRulesSearch::class;
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return ServersRules::class;
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

