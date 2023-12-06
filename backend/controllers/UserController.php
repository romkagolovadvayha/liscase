<?php

namespace backend\controllers;

use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\RoleForm;
use common\components\helpers\Role;
use common\components\queue\openAi\GenUsersJob;
use common\models\blog\BlogCategory;
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
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_SUPPORT, Role::ROLE_ACCOUNT_MANAGER, Role::ROLE_SALES, Role::ROLE_COURSE_EDITOR],
                    ],
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_SUPPORT, Role::ROLE_ACCOUNT_MANAGER],
                        'actions' => ['profile']
                    ]
                ],
            ],
        ]);
    }

    public function actions()
    {
        return ArrayHelper::merge(parent::actions(), [
            'editable' => [
                'class'      => EditableAction::class,
                'modelClass' => User::class,
            ],
        ]);
    }

    protected function _getSearchClassName()
    {
        return UserSearch::class;
    }

    public function actionGenerate()
    {
//        $cacheKey  = 'actionGenerate_Users';
//        $cacheData = Yii::$app->cache->get($cacheKey);
//        if (!empty($cacheData)) {
//            Yii::$app->session->addFlash('success', 'Процесс генерации уже запущен, ожидайте.');
//            return $this->redirect(['/user/index']);
//        }

        Yii::$app->queueOpenAi->push(new GenUsersJob());
        Yii::$app->session->addFlash('success', 'Процесс генерации запущен, ожидайте.');
//        Yii::$app->cache->set($cacheKey, $cacheKey, 6 * 60 * 60);
        return $this->redirect(['/user/index']);
    }

    public function actionProfile($userId)
    {
        $user = User::findOne($userId);
        $roleForm = new RoleForm();
        $roleForm->setUserId($userId);
        $bonusForm = new BonusForm();
        $bonusForm->setUserId($userId);
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
        return $this->render('profile', [
            'user' => $user,
            'roleForm' => $roleForm,
            'bonusForm' => $bonusForm,
        ]);
    }

    public function actionConfirmPhone($id)
    {
        $user = User::findOne($id);
        $user->userProfile->setPhoneIsConfirmed();

        return $this->redirect($this->getIndexUrl());
    }

    public function actionResetPhone($id)
    {
        $user = User::findOne($id);

        $userProfile = $user->userProfile;

        $userProfile->phone            = null;
        $userProfile->phone_is_confirm = 0;
        $userProfile->confirm_status   = 0;
        $userProfile->confirm_date     = null;
        $userProfile->save();

        UserConfirmCode::deleteAll('user_id = ' . $id . ' AND type = ' . UserConfirmCode::TYPE_CONFIRM_PHONE);

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
