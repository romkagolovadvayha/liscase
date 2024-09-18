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
use yii\filters\VerbFilter;

/**
 * TelegramConstructorMessageController implements the CRUD actions for TelegramConstructorMessage model.
 */
class TelegramConstructorMessageController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
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
            Yii::$app->session->addFlash('success', Yii::t('common', 'Сообщение успешно сохранено!'));
            return $this->redirect(['index']);
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
            Yii::$app->session->addFlash('success', Yii::t('common', 'Сообщение успешно сохранено!'));
            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionGetMessagePreview($id)
    {
        $this->layout = '@common/views/layouts/content';
        return $this->render('preview', [
            'messageId' => $id,
        ]);
    }

    public function actionGetButton($languages, $titles, $index, $messageId = null, $url = null)
    {
        $this->layout = '@common/views/layouts/content';
        $languages = json_decode($languages, 1);
        $titles = json_decode($titles, 1);
        return $this->render('button', [
            'messageId' => $messageId,
            'url' => $url,
            'languages' => $languages,
            'titles' => $titles,
            'index' => $index,
        ]);
    }

    /**
     * @param $audienceId
     *
     * @return string
     */
    public function actionGetAudienceInfo($audienceId)
    {
        $this->layout = '@common/views/layouts/content';
        $audience = TelegramConstructor::getAudience($audienceId);
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
        $mailings = TelegramConstructor::find()
            ->andWhere(['telegram_constructor_message_id' => $id])
            ->all();
        /** @var TelegramConstructor $mailing */
        foreach ($mailings as $mailing) {
            $mailing->telegram_constructor_message_id = null;
            $mailing->save(false);
        }
        $this->findModel($id)->delete();

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
