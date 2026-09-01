<?php

namespace backend\controllers;

use backend\forms\TelegramConstructorMessageForm;
use backend\models\TelegramConstructor;
use common\components\helpers\Role;
use Yii;
use backend\models\TelegramConstructorMessage;
use backend\models\TelegramConstructorMessageSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;

/**
 * TelegramConstructorMessageController implements the CRUD actions for TelegramConstructorMessage model.
 */
class TelegramConstructorMessageController extends Controller
{

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST']],
            ],
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_CONTENT_MANAGER],
                        'actions' => ['index', 'view', 'create', 'update', 'get-message-preview', 'get-button'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Lists all TelegramConstructorMessage models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new TelegramConstructorMessageSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single TelegramConstructorMessage model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new TelegramConstructorMessage model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new TelegramConstructorMessageForm();

        if ($model->load(Yii::$app->request->post()) && $model->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Шаблон сохранён. Проверьте итоговый вид сообщения.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing TelegramConstructorMessage model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Изменения шаблона сохранены.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionGetMessagePreview($id)
    {
        $this->layout = '@common/views/layouts/content';
        $model = TelegramConstructorMessage::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Шаблон сообщения не найден.');
        }
        return $this->render('preview', [
            'model' => $model,
        ]);
    }

    public function actionGetButton($languages, $titles, $index, $messageId = null, $url = null)
    {
        $this->layout = '@common/views/layouts/content';
        $languages = json_decode($languages, 1);
        $titles = json_decode($titles, 1);
        if (!is_array($languages) || !is_array($titles) || (int)$index < 1) {
            throw new BadRequestHttpException('Некорректные данные кнопки.');
        }
        return $this->render('button', [
            'messageId' => $messageId,
            'url' => $url,
            'languages' => $languages,
            'titles' => $titles,
            'index' => (int)$index,
        ]);
    }

    /**
     * @param $audienceId
     *
     * @return string
     */
    public function actionGetAudienceInfo($audienceId, $botId = TelegramConstructor::PERSONAL_BOT)
    {
        $this->layout = '@common/views/layouts/content';
        $audience = TelegramConstructor::getAudience($audienceId, $botId);
        return $this->render('audienceInfo', [
            'count' => count($audience),
            'audienceId' => $audienceId,
        ]);
    }

    /**
     * Deletes an existing TelegramConstructorMessage model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $mailingsCount = TelegramConstructor::find()
            ->andWhere(['telegram_constructor_message_id' => $id])
            ->count();
        if ($mailingsCount > 0) {
            Yii::$app->session->addFlash('error', "Шаблон используется в рассылках ({$mailingsCount}). Сначала замените его в черновиках.");
            return $this->redirect(['index']);
        }
        $this->findModel($id)->delete();
        Yii::$app->session->addFlash('success', 'Шаблон удалён.');

        return $this->redirect(['index']);
    }

    /**
     * Finds the TelegramConstructorMessage model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return TelegramConstructorMessage the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = TelegramConstructorMessageForm::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
