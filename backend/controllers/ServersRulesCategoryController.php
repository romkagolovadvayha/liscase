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

    protected function getIndexHeaderActions()
    {
        return [
            [
                'label' => '<i class="fas fa-plus"></i> Создать категорию',
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
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
     * @param int|null $id
     * @return \common\components\base\Model|ServersRulesCategory
     * @throws \yii\web\NotFoundHttpException
     */
    protected function _getFormModel($id = null)
    {
        $formModel = parent::_getFormModel($id);
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Назад'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
        return $formModel;
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
                            'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT],
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

