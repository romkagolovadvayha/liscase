<?php

namespace backend\controllers;

use backend\components\CrudController;
use common\components\helpers\Role;
use common\models\user\UserDrop;
use common\models\user\UserDropSearch;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UserDropController extends CrudController
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

    protected function _getSearchClassName()
    {
        return UserDropSearch::class;
    }

    /**
     * Изменение статуса одного предмета
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionUpdateStatus($id)
    {
        $model = $this->findModel($id);
        
        if (Yii::$app->request->isPost) {
            $status = Yii::$app->request->post('status');
            
            if ($status !== null && array_key_exists($status, UserDrop::getStatusList())) {
                $model->status = (int)$status;
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', 'Статус успешно изменен!');
                } else {
                    Yii::$app->session->setFlash('error', 'Ошибка при изменении статуса: ' . implode(', ', $model->getFirstErrors()));
                }
            } else {
                Yii::$app->session->setFlash('error', 'Неверный статус!');
            }
        }
        
        return $this->redirect($this->getIndexUrl());
    }

    /**
     * Массовое изменение статусов
     * @return Response
     */
    public function actionBulkUpdateStatus()
    {
        if (Yii::$app->request->isPost) {
            $ids = Yii::$app->request->post('ids', []);
            $status = Yii::$app->request->post('status');
            
            if (empty($ids) || !is_array($ids)) {
                Yii::$app->session->setFlash('error', 'Не выбраны элементы для изменения!');
                return $this->redirect($this->getIndexUrl());
            }
            
            if ($status === null || !array_key_exists($status, UserDrop::getStatusList())) {
                Yii::$app->session->setFlash('error', 'Неверный статус!');
                return $this->redirect($this->getIndexUrl());
            }
            
            $count = UserDrop::updateAll(
                ['status' => (int)$status],
                ['id' => $ids]
            );
            
            if ($count > 0) {
                Yii::$app->session->setFlash('success', "Статус успешно изменен для {$count} элементов!");
            } else {
                Yii::$app->session->setFlash('error', 'Не удалось изменить статус!');
            }
        }
        
        return $this->redirect($this->getIndexUrl());
    }

    /**
     * @param int $id
     * @return UserDrop
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        $model = UserDrop::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Запись не найдена.');
        }
        return $model;
    }
}

