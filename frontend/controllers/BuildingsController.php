<?php

namespace frontend\controllers;

use common\components\helpers\Role;
use common\controllers\WebController;
use common\models\building\Building;
use common\models\building\BuildingLike;
use frontend\forms\buildings\BuildingForm;
use frontend\models\building\BuildingSearch;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * BuildingController implements the CRUD actions for Building model.
 */
class BuildingsController extends WebController
{

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['index', 'view']
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'actions' => ['create', 'like', 'delete']
                    ]
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Lists all Building models.
     *
     * @return string
     */
    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_buildings')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $searchModel = new BuildingSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);
        $this->view->params['page'] = 'buildings';

        $this->view->params['meta_description'] = Yii::t('common', "Смотрите лучшие постройки игроков в Rust! На этой странице вы можете выкладывать свои творения, оценивать работы других игроков и находить вдохновение для новых проектов. Покажите свои строительные навыки, получите признание сообщества и узнайте, как создаются уникальные базы, форты и сооружения в Rust!");

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Building model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        if (!Yii::$app->settings->get('section_buildings')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $this->view->params['page'] = 'buildings';
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    public function actionLike($id)
    {
        if (!Yii::$app->settings->get('section_buildings')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        if (Yii::$app->request->method !== 'POST') {
            return "{'status': 'success'}";
        }
        /** @var Building $model */
        $model = Building::findOne($id);
        if (empty($model)) {
            return "{'status': 'success'}";
        }

        $userLike = BuildingLike::find()
                                ->andWhere(['user_id' => Yii::$app->user->id])
                                ->andWhere(['building_id' => $model->id])
                                ->one();
        if (!empty($userLike)) {
            $model->likes -= 1;
            $model->save();
            $userLike->delete();
        } else {
            $like = new BuildingLike();
            $like->user_id = Yii::$app->user->id;
            $like->building_id = $model->id;
            $like->type = BuildingLike::TYPE_LIKE;
            $like->created_at = date('Y-m-d H:i:s');
            $like->save();
            $model->likes += 1;
            $model->save();
        }

        return "{'status': 'success'}";
    }

    /**
     * Creates a new Building model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        if (!Yii::$app->settings->get('section_buildings')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $userBuildingsWait = Building::find()
            ->andWhere(['user_id' => Yii::$app->user->id])
            ->andWhere(['status' => Building::STATUS_WAIT])
            ->exists();
        if ($userBuildingsWait) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Вы не можете добавить новую постройку, пока у вас есть постройки на модерации!'));
            return $this->redirect(['index']);
        }

        /** @var \common\models\user\User $user */
        $user = Yii::$app->user->identity;
        if (empty($user->server)) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Добавить сервер могут только игроки!'));
            return $this->redirect(['index']);
        }

        $model = new BuildingForm();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->saveRecord()) {
                Yii::$app->session->addFlash('success', Yii::t('common', 'Постройка успешно отправлена на проверку!'));
                return $this->redirect(['index']);
            }
        } else {
            $model->loadDefaultValues();
        }

        $this->view->params['page'] = 'buildings';
        return $this->render('create', [
            'model' => $model,
            'server' => $user->server,
        ]);
    }

    /**
     * Deletes an existing Building model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model->status !== Building::STATUS_WAIT || $model->user_id !== Yii::$app->user->id) {
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Удаление запрещено!'));
            return $this->redirect(['index']);
        }

        foreach ($model->buildingResident as $resident) {
            $resident->delete();
        }
        foreach ($model->buildingImage as $image) {
            $uploadDir = Yii::getAlias('@app/web/uploads');
            $filePath = $uploadDir . "/buildings/" . $image->image;
            $filePathPreview = $uploadDir . "/buildings/preview_" . $image->image;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            if (file_exists($filePathPreview)) {
                unlink($filePathPreview);
            }
            $image->delete();
        }
        foreach ($model->buildingLikes as $like) {
            $like->delete();
        }

        $model->delete();

        Yii::$app->session->addFlash('success', Yii::t('common', 'Запись успешно удалена!'));
        return $this->redirect(['index']);
    }

    /**
     * Finds the Building model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Building the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BuildingForm::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
