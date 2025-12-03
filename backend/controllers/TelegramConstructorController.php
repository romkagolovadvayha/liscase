<?php

namespace backend\controllers;

use backend\models\AudienceSearch;
use backend\models\TelegramConstructor;
use backend\models\TelegramConstructorSearch;
use common\components\base\Model;
use common\components\helpers\Role;
use common\components\queue\telegram\TelegramConstructorSendJob;
use common\components\queue\telegram\UpdateTelegramAudienceJob;
use common\components\queue\vk\UpdateVkAudienceJob;
use common\components\telegram\TelegramPersonalBot;
use common\components\vk\VkApiHelper;
use common\models\user\User;
use common\models\user\UserSocialNetwork;
use common\models\vk\VkUser;
use kartik\form\ActiveForm;
use PHPUnit\Exception;
use Yii;
use yii\base\BaseObject;
use yii\data\ActiveDataProvider;
use yii\db\StaleObjectException;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\web\Response;

class TelegramConstructorController extends \backend\components\CrudController
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
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_CONTENT_MANAGER],
                        'actions' => ['index', 'audience', 'create', 'update', 'view']
                    ],
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                        'actions' => ['update-vk-audience', 'update-telegram-audience']
                    ],
                ],
            ],
        ];
    }

    /**
     * @return string
     */
    protected function _getFormLayout()
    {
        return '@backend/views/layouts/main';
    }

    /**
     * @return mixed
     */
    protected function _getSearchClassName()
    {
        return TelegramConstructorSearch::class;
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return TelegramConstructor::class;
    }

    /**
     * @param Model $formModel
     * @param string $view
     *
     * @return string|array|\yii\web\Response
     */
    protected function _saveForm($formModel, $view)
    {
        if ($formModel->load(Yii::$app->request->post())) {
            //   \Yii::info('post ' . print_r(Yii::$app->request->post(),1), 'problem');
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return \yii\bootstrap5\ActiveForm::validate($formModel);
            }
            
            // Валидация модели
            if (!$formModel->validate()) {
                Yii::$app->session->addFlash('error', 'Ошибка валидации: ' . json_encode($formModel->errors, JSON_UNESCAPED_UNICODE));
            } elseif ($formModel->saveRecord()) {
                Yii::$app->session->addFlash('success', 'Рассылка успешно создана!');
                return $this->redirect($this->getIndexUrl());
            } else {
                Yii::$app->session->addFlash('error', 'Не удалось сохранить рассылку. Проверьте данные.');
            }
        }
        return $this->render($view, [
            'model' => $formModel,
        ]);
    }

    /**
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function actionDelete($id)
    {
        $formModel = TelegramConstructor::findOne($id);
        if (!empty($formModel)) {
            $formModel->delete();
        }
        return $this->redirect(['index']);
    }

    /**
     * @param $id
     * @return Response
     */
    public function actionPlay($id): Response
    {
        $model = TelegramConstructor::findOne($id);
        if (empty($model)) {
            Yii::$app->session->addFlash('error', 'Рассылка не найдена');
            return $this->redirect($this->getIndexUrl());
        }

        // Проверяем, что рассылка не уже в процессе
        if ($model->status === TelegramConstructor::STATUS_IN_PROGRESS) {
            Yii::$app->session->addFlash('warning', 'Рассылка уже выполняется');
            return $this->redirect($this->getIndexUrl());
        }

        try {
            // Добавляем задачу в очередь
            Yii::$app->queueTelegram->push(new TelegramConstructorSendJob([
                'constructorId' => $id,
            ]));
            
            Yii::$app->session->addFlash('success', 
                'Рассылка добавлена в очередь. Статус будет обновлен после завершения.'
            );
        } catch (\Exception $e) {
            Yii::$app->session->addFlash('error', 'Ошибка при добавлении рассылки в очередь: ' . $e->getMessage());
            Yii::error("TelegramConstructor actionPlay error: " . $e->getMessage(), __METHOD__);
        }

        return $this->redirect($this->getIndexUrl());
    }

    /**
     * @param ActiveDataProvider $dataProvider
     *
     * @return string
     */
    protected function _renderIndex($dataProvider)
    {
        $countTelegramUsers = User::find()->andWhere('telegram_chat_id IS NOT NULL')->andWhere(['is_telegram_blocked' => 0])->count();
        $countVkUsers = VkUser::find()->where(['can_send_message' => true])->count();

        return $this->render('index', [
            'searchModel'  => $this->_searchModel,
            'dataProvider' => $dataProvider,
            'countTelegramUsers' => $countTelegramUsers,
            'countVkUsers' => $countVkUsers,
        ]);
    }

    public function actionAudience($id)
    {
        $model = TelegramConstructor::findOne($id);
        if (empty($model)) {
            return $this->redirect($this->getIndexUrl());
        }

        $searchModel = new AudienceSearch();
        $userIds = TelegramConstructor::getAudience($model->audience_id, $model->bot_id);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, null, $userIds);

        return $this->render('audience', [
            'audienceId' => $model->audience_id,
            'audienceCount' => count($userIds),
            'audience' => $userIds,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Предпросмотр аудитории перед созданием рассылки
     * @return string
     */
    public function actionPreviewAudience()
    {
        $botId = (int)Yii::$app->request->get('bot_id');
        $audienceId = (int)Yii::$app->request->get('audience_id');

        if (empty($botId) || empty($audienceId)) {
            return $this->render('audience', [
                'audienceId' => 0,
                'audienceCount' => 0,
                'audience' => [],
                'searchModel' => new AudienceSearch(),
                'dataProvider' => new \yii\data\ActiveDataProvider(['query' => User::find()->where('1=0')]),
                'isVk' => false,
            ]);
        }

        $searchModel = new AudienceSearch();
        $userIds = TelegramConstructor::getAudience($audienceId, $botId);
        
        // Для VK группы получаем User IDs из VK user IDs
        if ($botId == TelegramConstructor::VK_GROUP) {
            if (empty($userIds)) {
                return $this->render('audience-vk', [
                    'audienceId' => $audienceId,
                    'audienceCount' => 0,
                    'vkUsers' => [],
                ]);
            }
            
            // VK user IDs нужно преобразовать в User IDs
            // Но так как VK пользователи не связаны напрямую с User, показываем VK пользователей отдельно
            $vkUsers = VkUser::find()
                ->where(['IN', 'vk_user_id', $userIds])
                ->all();
            
            return $this->render('audience-vk', [
                'audienceId' => $audienceId,
                'audienceCount' => count($userIds),
                'vkUsers' => $vkUsers,
            ]);
        }
        
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, null, $userIds);

        return $this->render('audience', [
            'audienceId' => $audienceId,
            'audienceCount' => count($userIds),
            'audience' => $userIds,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'isVk' => false,
        ]);
    }

    /**
     * Обновление аудитории ВКонтакте
     * @return Response
     */
    public function actionUpdateVkAudience()
    {
        $groupId = Yii::$app->settings->get('vk_group_id');
        if (empty($groupId)) {
            Yii::$app->session->addFlash('error', 'VK group_id не настроен в настройках');
            return $this->redirect($this->getIndexUrl());
        }

        try {
            Yii::$app->queueProcess->push(new UpdateVkAudienceJob([
                'groupId' => $groupId,
            ]));
            
            Yii::$app->session->addFlash('success', 
                'Задача обновления аудитории ВКонтакте добавлена в очередь. Результат будет отправлен в Telegram.'
            );
        } catch (\Exception $e) {
            Yii::$app->session->addFlash('error', 'Ошибка при добавлении задачи в очередь: ' . $e->getMessage());
            Yii::error("UpdateVkAudience error: " . $e->getMessage(), __METHOD__);
        }

        return $this->redirect($this->getIndexUrl());
    }

    /**
     * Обновление счетчика Telegram получателей с проверкой блокировок
     * @return Response
     */
    public function actionUpdateTelegramAudience()
    {
        try {
            Yii::$app->queueTelegram->push(new UpdateTelegramAudienceJob());
            
            Yii::$app->session->addFlash('success', 
                'Задача проверки Telegram аудитории добавлена в очередь. Результат будет отправлен в Telegram.'
            );
        } catch (\Exception $e) {
            Yii::$app->session->addFlash('error', 'Ошибка при добавлении задачи в очередь: ' . $e->getMessage());
            Yii::error("UpdateTelegramAudience error: " . $e->getMessage(), __METHOD__);
        }

        return $this->redirect($this->getIndexUrl());
    }
}