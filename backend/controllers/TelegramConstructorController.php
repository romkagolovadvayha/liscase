<?php

namespace backend\controllers;

use backend\models\AudienceSearch;
use backend\models\TelegramConstructor;
use backend\models\TelegramConstructorSearch;
use common\components\base\Model;
use common\components\helpers\Role;
use common\components\telegram\TelegramPersonalBot;
use common\models\user\User;
use common\models\user\UserSocialNetwork;
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
            if ($formModel->saveRecord()) {
                return $this->redirect($this->getIndexUrl());
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
            return $this->redirect($this->getIndexUrl());
        }

        $model->status = TelegramConstructor::STATUS_IN_PROGRESS;
        $model->save();

        if($model->bot_id === TelegramConstructor::PERSONAL_BOT) {
            try{
                if($model->sendPersonalBot()) {
                    $model->status = TelegramConstructor::STATUS_SUCCESS;
                } else {
                    $model->status = TelegramConstructor::STATUS_ERROR;
                }
            } catch (\Exception $e) {
                $model->status = TelegramConstructor::STATUS_ERROR;
                \Yii::info("send telegram message error, id - $id, error message:  " . print_r($e->getMessage(), 1), 'problem');
                $model->save();
                return $this->redirect($this->getIndexUrl());
            }
            $model->save();
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
        $countTelegramUsers = User::find()->andWhere('telegram_chat_id IS NOT NULL')->count();

        return $this->render('index', [
            'searchModel'  => $this->_searchModel,
            'dataProvider' => $dataProvider,
            'countTelegramUsers' => $countTelegramUsers,
        ]);
    }

    public function actionAudience($id)
    {
        $searchModel = new AudienceSearch();
        $userIds = TelegramConstructor::getAudience($id);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, null, $userIds);

        return $this->render('audience', [
            'audienceId' => $id,
            'audienceCount' => count($userIds),
            'audience' => $userIds,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
}