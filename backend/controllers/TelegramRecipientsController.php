<?php

namespace backend\controllers;

use backend\models\TelegramConstructor;
use backend\models\TelegramRecipients;
use backend\models\TelegramRecipientsSearch;
use common\components\base\Model;
use common\components\helpers\Role;
use common\models\user\User;
use kartik\form\ActiveForm;
use Yii;
use yii\db\StaleObjectException;
use yii\web\Response;

class TelegramRecipientsController extends \backend\components\CrudController
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
     * @return mixed
     */
    protected function _getSearchClassName()
    {
        // обновляем численность групп по языкам в лк
        foreach (TelegramConstructor::getlkLanguagesArr() as $key=>$item){
            $countUsers = User::find()->cache(30)->where(['current_language' => $item, 'status' => User::STATUS_ACTIVE])->count();
            $telegramRecipientsModel = TelegramRecipients::findOne(['name'=>$key]);
            if($telegramRecipientsModel !== null){
                $telegramRecipientsModel->quantity = $countUsers;
                $telegramRecipientsModel->save();
            }
        }
        return TelegramRecipientsSearch::class;
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return TelegramRecipients::class;
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
                return ActiveForm::validate($formModel);
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
    public function actionDelete($id): string
    {
        $formModel = TelegramRecipients::findOne($id);
        if ($formModel !== null) {
            $formModel->delete();
        }
        $this->_setSearchModel();
        $this->_rememberIndexUrl();
        return $this->_renderIndex($this->_getSearchDataProvider());
    }
}