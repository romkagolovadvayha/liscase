<?php

namespace frontend\controllers;

use common\models\support\Support;
use frontend\forms\buildings\BuildingForm;
use frontend\forms\support\SupportForm;
use frontend\models\support\SupportSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * SupportController implements the CRUD actions for Support model.
 */
class SupportController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all Support models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new SupportSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Support model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionTicket($id)
    {
        $user = \Yii::$app->user->identity;
        return $this->render('view', [
            'model' => $this->findModel($id),
            'user' => $user,
        ]);
    }

    /**
     * Creates a new Support model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new SupportForm();

        if ($this->request->isPost) {
            $ticket = Support::find()
                ->andWhere(['user_id' => \Yii::$app->user->id])
                ->andWhere(['status' => Support::STATUS_OPEN])
                ->one();
            if (!empty($ticket)) {
                return $this->redirect(['ticket', 'id' => $ticket->id]);
            }
            if ($model->load($this->request->post()) && $model->saveRecord()) {
                return $this->redirect(['ticket', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->renderAjax('create', [
            'model' => $model,
        ]);
    }

    /**
     * Finds the Support model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Support the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($number)
    {
        if (($model = Support::findByNumber($number)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
