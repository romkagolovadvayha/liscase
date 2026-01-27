<?php

namespace backend\controllers;

use backend\components\CrudController;
use backend\models\ServersTagsSearch;
use common\components\helpers\Role;
use common\models\servers\ServersTags;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use Yii;

/**
 * ServersTagsController implements the CRUD actions for ServersTags model.
 */
class ServersTagsController extends CrudController
{
    public $enableCsrfValidation = false; // Временно для отладки
    
    /**
     * @return string
     */
    protected function _getSearchClassName()
    {
        return ServersTagsSearch::class;
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return ServersTags::class;
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

    /**
     * Creates a new ServersTags model.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new ServersTags();
        
        // Проверка CSRF вручную для отладки
        if (Yii::$app->request->isPost) {
            $csrfToken = Yii::$app->request->post(Yii::$app->request->csrfParam);
            $validCsrf = Yii::$app->request->validateCsrfToken($csrfToken);
            
            Yii::info('POST Request to create tag', __METHOD__);
            Yii::info('CSRF Token valid: ' . ($validCsrf ? 'YES' : 'NO'), __METHOD__);
            
            if (!$validCsrf) {
                Yii::error('CSRF validation failed!', __METHOD__);
                Yii::$app->session->setFlash('error', 'CSRF токен недействителен. Попробуйте обновить страницу.');
                return $this->render('create', ['model' => $model]);
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            Yii::info('Model loaded successfully', __METHOD__);
            
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Тег успешно создан');
                return $this->redirect(['view', 'id' => $model->id]);
            } else {
                Yii::error('Model save failed: ' . print_r($model->getErrors(), true), __METHOD__);
                Yii::$app->session->setFlash('error', 'Ошибка сохранения: ' . print_r($model->getErrors(), true));
            }
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Displays a single ServersTags model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Deletes an existing ServersTags model.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Тег успешно удален');
        return $this->redirect(['index']);
    }

    /**
     * Finds the ServersTags model based on its primary key value.
     * @param int $id ID
     * @return ServersTags the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ServersTags::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}

