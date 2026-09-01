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
use common\models\user\User;
use common\models\user\UserSocialNetwork;
use common\models\vk\VkUser;
use kartik\form\ActiveForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\StaleObjectException;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

class TelegramConstructorController extends \backend\components\CrudController
{

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'play' => ['POST'],
                    'duplicate' => ['POST'],
                    'update-vk-audience' => ['POST'],
                    'update-telegram-audience' => ['POST'],
                ],
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
                        'actions' => ['index', 'audience', 'create', 'update', 'view', 'duplicate', 'get-audience-count', 'preview-audience']
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

    public function actionCreate()
    {
        $model = $this->_getFormModel();
        if (!Yii::$app->request->isPost) {
            $templateId = (int)Yii::$app->request->get('template');
            if ($templateId > 0 && \backend\models\TelegramConstructorMessage::find()->andWhere(['id' => $templateId])->exists()) {
                $model->telegram_constructor_message_id = $templateId;
            }

            $botId = (int)Yii::$app->request->get('bot');
            if (array_key_exists($botId, TelegramConstructor::getBotList())) {
                $model->bot_id = $botId;
            }

            $audienceId = (int)Yii::$app->request->get('audience');
            if (array_key_exists($audienceId, TelegramConstructor::getAudienceList())) {
                $model->audience_id = $audienceId;
            }
        }

        return $this->_saveForm($model, 'create');
    }

    /**
     * @param Model $formModel
     * @param string $view
     *
     * @return string|array|\yii\web\Response
     */
    protected function _saveForm($formModel, $view)
    {
        $isNewRecord = $formModel->isNewRecord;
        if ($formModel->load(Yii::$app->request->post())) {
            //   \Yii::info('post ' . print_r(Yii::$app->request->post(),1), 'problem');
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return \yii\bootstrap5\ActiveForm::validate($formModel);
            }

            // Валидация модели
            if (!$formModel->validate()) {
                Yii::$app->session->addFlash('error', 'Проверьте отмеченные поля. Черновик не сохранён.');
            } elseif ($formModel->saveRecord()) {
                Yii::$app->session->addFlash('success', $isNewRecord ? 'Черновик создан. Проверьте параметры перед запуском.' : 'Изменения сохранены.');
                return $this->redirect(['view', 'id' => $formModel->id]);
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
        $formModel = $this->findMailing($id);
        if ($formModel->status !== TelegramConstructor::STATUS_NEW) {
            Yii::$app->session->addFlash('error', 'Удалять можно только черновики рассылок.');
            return $this->redirect(['view', 'id' => $formModel->id]);
        }
        $formModel->delete();
        Yii::$app->session->addFlash('success', 'Черновик удалён.');
        return $this->redirect(['index']);
    }

    public function actionDuplicate($id): Response
    {
        $source = $this->findMailing($id);
        $copy = new TelegramConstructor();
        $copy->bot_id = $source->bot_id;
        $copy->audience_id = $source->audience_id;
        $copy->telegram_constructor_message_id = $source->telegram_constructor_message_id;
        $copy->only_with_user = $source->only_with_user;
        $copy->title = mb_substr('Копия: ' . $source->title, 0, 190);

        if (!$copy->validate() || !$copy->saveRecord()) {
            Yii::$app->session->addFlash('error', 'Не удалось создать копию. Проверьте, что шаблон и аудитория ещё доступны.');
            return $this->redirect(['view', 'id' => $source->id]);
        }

        Yii::$app->session->addFlash('success', 'Создан новый черновик с теми же параметрами.');
        return $this->redirect(['update', 'id' => $copy->id]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findMailing($id);
        if ($model->status !== TelegramConstructor::STATUS_NEW) {
            Yii::$app->session->addFlash('warning', 'Отправленную или выполняющуюся рассылку нельзя изменять.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return parent::actionUpdate($id);
    }

    public function actionView($id)
    {
        $model = $this->findMailing($id);
        $audienceCount = TelegramConstructor::getAudienceCount(
            $model->audience_id,
            $model->bot_id,
            $model->bot_id === TelegramConstructor::VK_GROUP && !empty($model->only_with_user)
        );

        return $this->render('view', [
            'model' => $model,
            'audienceCount' => $audienceCount,
        ]);
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

        if ($model->status !== TelegramConstructor::STATUS_NEW) {
            Yii::$app->session->addFlash('warning', 'Эту рассылку уже запускали. Повторный запуск заблокирован.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        if ($model->telegramConstructorMessage === null) {
            Yii::$app->session->addFlash('error', 'Шаблон сообщения удалён. Выберите другой шаблон.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        $recipientsCount = TelegramConstructor::getAudienceCount(
            $model->audience_id,
            $model->bot_id,
            $model->bot_id === TelegramConstructor::VK_GROUP && !empty($model->only_with_user)
        );
        if ($recipientsCount === 0) {
            Yii::$app->session->addFlash('error', 'В выбранной аудитории нет доступных получателей. Рассылка не запущена.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        try {
            // Atomically reserve the draft so parallel requests cannot queue it twice.
            $reserved = TelegramConstructor::updateAll(
                ['status' => TelegramConstructor::STATUS_IN_PROGRESS],
                ['id' => $model->id, 'status' => TelegramConstructor::STATUS_NEW]
            );
            if ($reserved !== 1) {
                Yii::$app->session->addFlash('warning', 'Рассылку уже запустили в другом запросе.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
            $model->status = TelegramConstructor::STATUS_IN_PROGRESS;

            // Добавляем задачу в очередь
            if ($model->bot_id == TelegramConstructor::PERSONAL_BOT) {
                Yii::$app->queueTelegram->push(new TelegramConstructorSendJob([
                                                                                  'constructorId' => $id,
                                                                              ]));
            } else {
                Yii::$app->queueVk->push(new TelegramConstructorSendJob([
                                                                                  'constructorId' => $id,
                                                                              ]));
            }

            Yii::$app->session->addFlash('success',
                'Рассылка добавлена в очередь. Статус будет обновлен после завершения.'
            );
        } catch (\Throwable $e) {
            TelegramConstructor::updateAll(
                ['status' => TelegramConstructor::STATUS_NEW],
                ['id' => $model->id, 'status' => TelegramConstructor::STATUS_IN_PROGRESS]
            );
            $model->status = TelegramConstructor::STATUS_NEW;
            Yii::$app->session->addFlash('error', 'Ошибка при добавлении рассылки в очередь: ' . $e->getMessage());
            Yii::error("TelegramConstructor actionPlay error: " . $e->getMessage(), __METHOD__);
        }

        return $this->redirect(['view', 'id' => $model->id]);
    }

    /**
     * @param ActiveDataProvider $dataProvider
     *
     * @return string
     */
    protected function _renderIndex($dataProvider)
    {
        $this->view->params['showFilters'] = false;
        $countTelegramUsers = User::find()
            ->andWhere(['status' => User::STATUS_ACTIVE, 'is_telegram_blocked' => 0])
            ->andWhere(['IS NOT', 'telegram_chat_id', null])
            ->count();
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
        // Передаем only_with_user только для VK группы
        $onlyWithUser = ($model->bot_id == TelegramConstructor::VK_GROUP) && !empty($model->only_with_user);

        if ($model->bot_id === TelegramConstructor::VK_GROUP) {
            $userIds = TelegramConstructor::getAudience($model->audience_id, $model->bot_id, $onlyWithUser);
            $vkUsers = empty($userIds) ? [] : VkUser::find()->andWhere(['vk_user_id' => $userIds])->all();
            return $this->render('audience-vk', [
                'audienceId' => $model->audience_id,
                'audienceCount' => count($userIds),
                'vkUsers' => $vkUsers,
                'mailingId' => $model->id,
            ]);
        }

        $audienceQuery = TelegramConstructor::getTelegramAudienceQuery($model->audience_id);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, null, $audienceQuery);

        return $this->render('audience', [
            'audienceId' => $model->audience_id,
            'audienceCount' => TelegramConstructor::getAudienceCount($model->audience_id, $model->bot_id),
            'audience' => [],
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'mailingId' => $model->id,
        ]);
    }

    /**
     * Получение количества получателей для рассылки (AJAX)
     * @return Response
     */
    public function actionGetAudienceCount()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $botId = (int)Yii::$app->request->get('bot_id');
        $audienceId = (int)Yii::$app->request->get('audience_id');
        $onlyWithUser = (bool)Yii::$app->request->get('only_with_user', false);

        if (empty($botId) || empty($audienceId)) {
            return ['success' => false, 'count' => 0, 'message' => 'Не указаны параметры'];
        }

        // Применяем фильтр only_with_user только для VK группы
        $onlyWithUser = ($botId == TelegramConstructor::VK_GROUP) && $onlyWithUser;

        $count = TelegramConstructor::getAudienceCount($audienceId, $botId, $onlyWithUser);

        return [
            'success' => true,
            'count' => $count,
            'formatted' => number_format($count, 0, ',', ' ')
        ];
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

        // Для предпросмотра аудитории нужно получить only_with_user из запроса (только для VK)
        $onlyWithUser = ($botId == TelegramConstructor::VK_GROUP) && (bool)Yii::$app->request->get('only_with_user', false);

        // Для VK группы получаем User IDs из VK user IDs
        if ($botId == TelegramConstructor::VK_GROUP) {
            $userIds = TelegramConstructor::getAudience($audienceId, $botId, $onlyWithUser);
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

        $audienceQuery = TelegramConstructor::getTelegramAudienceQuery($audienceId);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, null, $audienceQuery);

        return $this->render('audience', [
            'audienceId' => $audienceId,
            'audienceCount' => TelegramConstructor::getAudienceCount($audienceId, $botId),
            'audience' => [],
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

    private function findMailing($id): TelegramConstructor
    {
        $model = TelegramConstructor::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Рассылка не найдена.');
        }
        return $model;
    }
}
