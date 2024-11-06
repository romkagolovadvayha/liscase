<?php

namespace frontend\controllers;

use common\components\helpers\Role;
use Yii;
use common\models\support\Support;
use common\models\support\SupportMessage;
use frontend\forms\buildings\BuildingForm;
use frontend\forms\support\SupportForm;
use frontend\models\support\SupportSearch;
use yii\base\BaseObject;
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
        return $this->renderAjax('view', [
            'model' => $this->findModel($id),
            'user' => $user,
        ]);
    }

    public function actionGetMessage($id)
    {
        $message = SupportMessage::findOne($id);
        if (empty($message) || (!Yii::$app->user->identity->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) && $message->support->user->id !== Yii::$app->user->id)) {
            throw new NotFoundHttpException('Not found');
        }
        return $this->renderAjax('_message', [
            'model' => $message
        ]);
    }

    /**
     * Creates a new Support model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionChat()
    {
        /** @var Support $ticket */
        $ticket = Support::find()
                         ->andWhere(['user_id' => \Yii::$app->user->id])
                         ->andWhere(['status' => Support::STATUS_OPEN])
                         ->one();
        if (!empty($ticket)) {
            return $this->redirect(['ticket', 'id' => $ticket->getNumber()]);
        }
        $model = new SupportForm();
        if ($model->saveRecord()) {
            return $this->redirect(['ticket', 'chatId' => $model->getNumber()]);
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
