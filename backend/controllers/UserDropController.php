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
     * Переопределяем метод для загрузки связей
     */
    protected function _getSearchDataProvider()
    {
        $dataProvider = $this->_searchModel->search(Yii::$app->request->queryParams);
        
        // Загружаем связи для всех моделей сразу (batch loading)
        $models = $dataProvider->getModels();
        $userIds = [];
        $dropIds = [];
        
        foreach ($models as $model) {
            if ($model->user_id) {
                $userIds[] = $model->user_id;
            }
            if ($model->drop_id) {
                $dropIds[] = $model->drop_id;
            }
        }
        
        // Загружаем всех пользователей одним запросом
        $users = [];
        if (!empty($userIds)) {
            $users = \common\models\user\User::find()
                ->where(['id' => array_unique($userIds)])
                ->indexBy('id')
                ->all();
        }
        
        // Загружаем все серверы одним запросом
        $serverIds = [];
        foreach ($users as $user) {
            if ($user->server_id) {
                $serverIds[] = $user->server_id;
            }
        }
        $servers = [];
        if (!empty($serverIds)) {
            $servers = \common\models\servers\Servers::find()
                ->where(['id' => array_unique($serverIds)])
                ->indexBy('id')
                ->all();
        }
        
        // Загружаем все предметы одним запросом
        $drops = [];
        if (!empty($dropIds)) {
            $drops = \common\models\box\Drop::find()
                ->where(['id' => array_unique($dropIds)])
                ->indexBy('id')
                ->all();
        }
        
        // Присваиваем связи к моделям
        foreach ($models as $model) {
            if ($model->user_id && isset($users[$model->user_id])) {
                $user = $users[$model->user_id];
                
                // Используем populateRelation для правильного присваивания связи
                $model->populateRelation('user', $user);
                
                // Присваиваем сервер к пользователю
                if ($user->server_id && isset($servers[$user->server_id])) {
                    $user->populateRelation('server', $servers[$user->server_id]);
                }
            }
            
            // Сохраняем drop в отдельном свойстве для быстрого доступа через Reflection
            if ($model->drop_id && isset($drops[$model->drop_id])) {
                $reflection = new \ReflectionClass($model);
                $attributesProperty = $reflection->getProperty('_attributes');
                $attributesProperty->setAccessible(true);
                $attributes = $attributesProperty->getValue($model);
                $attributes['_drop'] = $drops[$model->drop_id];
                $attributesProperty->setValue($model, $attributes);
            }
        }
        
        return $dataProvider;
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

