<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use common\models\battle_pass\BattlePassSeason;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

class BattlePassSeasonsController extends BackendController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [[
                    'allow' => true,
                    'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                ]],
            ],
        ];
    }

    public function actionIndex()
    {
        $models = BattlePassSeason::find()->with(['medal'])->orderBy(['season_number' => SORT_DESC])->all();
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [[
            'label' => '<i class="fas fa-plus"></i> Создать сезон',
            'url' => ['create'],
            'class' => 'ds-btn ds-btn--primary ds-btn--sm',
        ]];
        return $this->render('index', ['models' => $models]);
    }

    public function actionCreate()
    {
        $model = new BattlePassSeason();
        $model->status = BattlePassSeason::STATUS_DRAFT;
        $model->reward_type = 'item';
        $model->starts_at = date('Y-m-d H:i:s');
        return $this->saveModel($model);
    }

    public function actionUpdate($id)
    {
        return $this->saveModel($this->findModel((int)$id));
    }

    private function saveModel(BattlePassSeason $model)
    {
        if ($model->load(Yii::$app->request->post())) {
            foreach (['starts_at', 'ends_at'] as $attribute) {
                if ($model->{$attribute}) {
                    $model->{$attribute} = date('Y-m-d H:i:s', strtotime($model->{$attribute}));
                } elseif ($attribute === 'ends_at') {
                    $model->ends_at = null;
                }
            }
            if ($model->save()) {
                if ($model->status === BattlePassSeason::STATUS_ACTIVE) {
                    BattlePassSeason::updateAll(
                        ['status' => BattlePassSeason::STATUS_FINISHED],
                        ['and', ['status' => BattlePassSeason::STATUS_ACTIVE], ['<>', 'id', (int)$model->id]]
                    );
                }
                Yii::$app->session->setFlash('success', 'Сезон сохранён.');
                return $this->redirect(['index']);
            }
        }
        $this->view->params['contentClass'] = 'content-no-padding';
        return $this->render('_form', ['model' => $model]);
    }

    private function findModel(int $id): BattlePassSeason
    {
        $model = BattlePassSeason::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Сезон не найден.');
        }
        return $model;
    }
}
