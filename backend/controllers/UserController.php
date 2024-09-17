<?php

namespace backend\controllers;

use backend\forms\userProfile\BanForm;
use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\MuteForm;
use backend\forms\userProfile\PasswordForm;
use backend\forms\userProfile\PayoutForm;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\SkinForm;
use common\components\helpers\Role;
use common\models\rcon\RconTasks;
use common\models\user\UserChecking;
use common\models\user\UserSearch;
use Yii;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use common\models\user\User;
use backend\components\CrudController;
use yii2mod\editable\EditableAction;

class UserController extends CrudController
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
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                        'actions' => ['profile']
                    ]
                ],
            ],
        ]);
    }

    protected function _getSearchClassName()
    {
        return UserSearch::class;
    }

    public function actionProfile($userId)
    {
        $user = User::findOne($userId);
        $roleForm = new RoleForm();
        $roleForm->setUserId($userId);
        $bonusForm = new BonusForm();
        $bonusForm->setUserId($userId);
        $payoutForm = new PayoutForm();
        $payoutForm->setUserId($userId);
        $skinForm = new SkinForm();
        $skinForm->setUserId($userId);
        $banForm = new BanForm();
        $banForm->setUserId($userId);
        $muteForm = new MuteForm();
        $muteForm->setUserId($userId);
        $bodyParams = Yii::$app->request->bodyParams;
        if (!empty($bodyParams['RoleForm'])
            && $roleForm->load(Yii::$app->request->post())
            && $roleForm->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Роль пользователя успешно изменена!');
            return $this->redirect(['profile', 'userId' => $userId]);
        }
        if (!empty($bodyParams['BonusForm'])
            && $bonusForm->load(Yii::$app->request->post())
            && $bonusForm->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Бонус успешно начислен!');
            return $this->redirect(['profile', 'userId' => $userId]);
        }
        if (!empty($bodyParams['PayoutForm'])
            && $payoutForm->load(Yii::$app->request->post())
            && $payoutForm->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Вывод успешно проведен!');
            return $this->redirect(['profile', 'userId' => $userId]);
        }
        if (!empty($bodyParams['SkinForm'])
            && $skinForm->load(Yii::$app->request->post())
            && $skinForm->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Скин успешно отправлен!');
            return $this->redirect(['profile', 'userId' => $userId]);
        }
        if (!empty($bodyParams['BanForm'])
            && $banForm->load(Yii::$app->request->post())
            && $banForm->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Бан успешно выдан!');
            return $this->redirect(['profile', 'userId' => $userId]);
        }
        if (!empty($bodyParams['MuteForm'])
            && $muteForm->load(Yii::$app->request->post())
            && $muteForm->saveRecord()) {
            Yii::$app->session->addFlash('success', 'Мут успешно выдан!');
            return $this->redirect(['profile', 'userId' => $userId]);
        }
        return $this->render('profile', [
            'user' => $user,
            'roleForm' => $roleForm,
            'bonusForm' => $bonusForm,
            'skinForm' => $skinForm,
            'banForm' => $banForm,
            'muteForm' => $muteForm,
            'payoutForm' => $payoutForm,
        ]);
    }

    public function actionUnban($userId)
    {
        $user = User::findOne($userId);
        $user->unban();
        Yii::$app->session->addFlash('success', 'Бан успешно снят!');
        return $this->redirect('/user/profile?userId=' . $userId);
    }

    public function actionCheckingStart($userId)
    {
        $moder = Yii::$app->user->identity;
        $user = User::findOne($userId);
        $model = new UserChecking();
        $model->user_id = $user->id;
        $model->status = UserChecking::STATUS_CHECKING;
        $model->checking_by = $moder->id;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save();

        $command = "iqrs call2 \"{$user->steam_id}\" \"{$moder->discord}\"";
        RconTasks::execute($command);

        Yii::$app->session->addFlash('success', 'Игрок вызван на проверку!');
        return $this->redirect('/user/profile?userId=' . $userId);
    }

    public function actionCheckingStop($userId)
    {
        $user = User::findOne($userId);
        /** @var UserChecking $model */
        $model = UserChecking::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['status' => UserChecking::STATUS_CHECKING])
            ->one();
        $model->status = UserChecking::STATUS_DONE;
        $model->done_at = date('Y-m-d H:i:s');
        $model->save();

        $command = "iqrs dismiss2 \"{$user->steam_id}\"";
        RconTasks::execute($command);

        Yii::$app->session->addFlash('success', 'Проверка завершена!');
        return $this->redirect('/user/profile?userId=' . $userId);
    }

    public function actionConfirmPhone($id)
    {
        $user = User::findOne($id);
        $user->userProfile->setPhoneIsConfirmed();

        return $this->redirect($this->getIndexUrl());
    }

    public function actionSwitchIdentity($id)
    {
        $parentUserId = Yii::$app->user->id;

        $user = User::findOne($id);

        if (!$user->getAuthKey()) {
            $user->generateAuthKey();
            $user->save();
        }

        $url = Yii::$app->params['baseUrl'] . '/auth/switch-identity?authKey=' . $user->getAuthKey() . '&parentUser='
               . $parentUserId;

        return $this->redirect($url);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     */
    public function actionCalculate($id): \yii\web\Response
    {
        $agent = UserAgents::findOne(['user_id' => $id,'status' => 1]);
        if($agent !== null){
            $userAgentModel = new UserAgents();
            $userAgentModel->salary($agent);
        }
        return $this->goBack();
    }
}
