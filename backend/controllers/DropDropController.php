<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\blog\BlogImage;
use backend\models\blog\BlogImageSearch;
use common\models\box\DropDrop;
use common\models\box\DropStat;
use frontend\forms\blog\BlogImageForm;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * BlogImageController implements the CRUD actions for BlogImage model.
 */
class DropDropController extends Controller
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
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Creates a new BlogImage model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate($dropId)
    {
        $model = new DropDrop();
        $model->parent_drop_id = $dropId;
        $model->created_at = date('Y-m-d H:i:s');

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->save()) {
                // Если это AJAX запрос, возвращаем JSON для закрытия модалки
                if ($this->request->isAjax) {
                    \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                    return [
                        'success' => true,
                        'message' => 'Предмет успешно добавлен',
                        'dropId' => $dropId
                    ];
                }
                return $this->redirect(['/drop/update?id=' . $dropId]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->renderAjax('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing BlogImage model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->save()) {
            // Если это AJAX запрос, возвращаем JSON для закрытия модалки
            if ($this->request->isAjax) {
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return [
                    'success' => true,
                    'message' => 'Предмет успешно обновлен',
                    'dropId' => $model->parent_drop_id
                ];
            }
            return $this->redirect(['/drop/update?id=' . $model->parent_drop_id]);
        }

        return $this->renderAjax('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing BlogImage model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $dropId = $model->parent_drop_id;
        $model->delete();

        // Если это AJAX запрос, возвращаем JSON
        if ($this->request->isAjax) {
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return [
                'success' => true,
                'message' => 'Предмет успешно удален',
                'dropId' => $dropId
            ];
        }

        return $this->redirect(['/drop/update?id=' . $dropId]);
    }

    /**
     * Finds the BlogImage model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return DropDrop the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = DropDrop::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

}
