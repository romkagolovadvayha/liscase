<?php

namespace backend\controllers;

use backend\components\BackendController;
use backend\forms\tournament\TournamentForm;
use backend\models\TournamentSearch;
use common\components\helpers\Role;
use common\models\tournament\Tournament;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

/**
 * Управление турнирами в админке.
 */
class TournamentController extends BackendController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'view', 'create', 'update'],
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                    [
                        'allow' => true,
                        'actions' => ['delete'],
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new TournamentSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> ' . Yii::t('common', 'Новый турнир'),
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $model = new TournamentForm();

        if ($model->load(Yii::$app->request->post()) && $model->saveWithUploads()) {
            Yii::$app->session->setFlash('success', Yii::t('common', 'Турнир создан.'));
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'К списку'),
                'url' => ['index'],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('create', ['model' => $model]);
    }

    public function actionView($id): string
    {
        $model = $this->findModel($id);
        $frontendBase = rtrim((string)Yii::$app->params['baseUrl'], '/');
        $publicUrl = $model->status === Tournament::STATUS_PUBLISHED
            ? $frontendBase . '/tournaments/' . rawurlencode($model->slug)
            : null;

        $this->view->params['contentClass'] = 'content-no-padding';

        return $this->render('view', [
            'model' => $model,
            'publicUrl' => $publicUrl,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = TournamentForm::find()
            ->where(['id' => (int)$id, 'type' => Tournament::TYPE_CLAN])
            ->with('rewards')
            ->one();
        if (!$model) {
            throw new NotFoundHttpException('Tournament not found');
        }

        if ($model->load(Yii::$app->request->post()) && $model->saveWithUploads()) {
            Yii::$app->session->setFlash('success', Yii::t('common', 'Сохранено.'));
            return $this->redirect(['view', 'id' => $model->id]);
        }

        if (is_string($model->tags) && $model->tags !== '') {
            $decoded = json_decode($model->tags, true);
            if (is_array($decoded)) {
                $model->tags = implode(', ', $decoded);
            }
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Назад'),
                'url' => ['view', 'id' => $model->id],
                'class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model->status !== Tournament::STATUS_DRAFT) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Удалить можно только черновик.'));
            return $this->redirect(['view', 'id' => $id]);
        }
        $model->delete();
        Yii::$app->session->setFlash('success', Yii::t('common', 'Удалено.'));
        return $this->redirect(['index']);
    }

    protected function findModel($id): Tournament
    {
        $model = Tournament::find()
            ->where(['id' => (int)$id, 'type' => Tournament::TYPE_CLAN])
            ->with(['server', 'rewards'])
            ->one();
        if (!$model) {
            throw new NotFoundHttpException('Tournament not found');
        }
        return $model;
    }
}
