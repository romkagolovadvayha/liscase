<?php

namespace backend\controllers;

use backend\components\BackendController;
use backend\forms\tournament\CashRaceForm;
use common\components\helpers\Role;
use common\components\tournament\CashRaceService;
use common\models\tournament\CashRaceTournament;
use common\models\tournament\Tournament;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

class CashRaceController extends BackendController
{
    public function behaviors(): array
    {
        return [
            'access' => ['class' => AccessControl::class, 'rules' => [
                ['allow' => true, 'actions' => ['index', 'create', 'update', 'start', 'finish'], 'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR]],
                ['allow' => true, 'actions' => ['delete'], 'roles' => [Role::ROLE_ADMIN]],
            ]],
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['start' => ['POST'], 'finish' => ['POST'], 'delete' => ['POST']]],
        ];
    }

    public function actionIndex(): string
    {
        $models = Tournament::find()->where(['type' => Tournament::TYPE_CASH_RACE])->with(['server', 'cashRace'])->orderBy(['id' => SORT_DESC])->all();
        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['headerActions'] = [[
            'label' => '<i class="fas fa-plus"></i> Новая денежная гонка', 'url' => ['create'],
            'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium no-underline inline-flex items-center gap-1.5',
        ]];
        return $this->render('index', ['models' => $models]);
    }

    public function actionCreate()
    {
        $model = new CashRaceForm(['type' => Tournament::TYPE_CASH_RACE, 'title' => 'Денежная гонка', 'slug' => Tournament::generateSlug('denezhnaya-gonka'), 'status' => Tournament::STATUS_DRAFT, 'format_label' => 'Ключи из бочек']);
        if ($model->load(Yii::$app->request->post()) && $model->saveWithUploads()) {
            Yii::$app->session->setFlash('success', 'Денежная гонка создана.');
            return $this->redirect(['update', 'id' => $model->id]);
        }
        $this->view->params['contentClass'] = 'content-no-padding';
        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate($id)
    {
        $model = CashRaceForm::find()->where(['id' => (int)$id, 'type' => Tournament::TYPE_CASH_RACE])->with('rewards')->one();
        if (!$model) throw new NotFoundHttpException('Cash race not found');
        if ($model->load(Yii::$app->request->post()) && $model->saveWithUploads()) {
            Yii::$app->session->setFlash('success', 'Настройки сохранены.');
            return $this->redirect(['update', 'id' => $model->id]);
        }
        $this->view->params['contentClass'] = 'content-no-padding';
        return $this->render('update', ['model' => $model]);
    }

    public function actionStart($id)
    {
        $model = $this->findMaster($id);
        $model->status = Tournament::STATUS_PUBLISHED;
        $model->starts_at = date('Y-m-d H:i:s');
        if (strtotime((string)$model->ends_at) <= time()) $model->ends_at = date('Y-m-d H:i:s', time() + 86400);
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Гонка запущена. Плагин увидит её при следующем опросе.');
        return $this->redirect(['update', 'id' => $id]);
    }

    public function actionFinish($id)
    {
        $model = $this->findMaster($id);
        $model->ends_at = date('Y-m-d H:i:s', time() - 1);
        $model->save(false);
        $config = CashRaceTournament::findOne(['tournament_id' => $model->id]);
        if ($config) CashRaceService::finalize($config);
        Yii::$app->session->setFlash('success', 'Гонка завершена, места рассчитаны и медали начислены.');
        return $this->redirect(['update', 'id' => $id]);
    }

    public function actionDelete($id)
    {
        $model = $this->findMaster($id);
        if ($model->status !== Tournament::STATUS_DRAFT) throw new \DomainException('Удалить можно только черновик');
        $model->delete();
        return $this->redirect(['index']);
    }

    private function findMaster($id): Tournament
    {
        $model = Tournament::findOne(['id' => (int)$id, 'type' => Tournament::TYPE_CASH_RACE]);
        if (!$model) throw new NotFoundHttpException('Cash race not found');
        return $model;
    }
}
