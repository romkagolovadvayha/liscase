<?php

namespace backend\controllers;

use backend\forms\userProfile\BanForm;
use backend\forms\userProfile\BonusForm;
use backend\forms\userProfile\MuteForm;
use backend\forms\userProfile\PayoutForm;
use backend\forms\userProfile\RoleForm;
use backend\forms\userProfile\SkinForm;
use common\components\helpers\Role;
use common\models\rcon\RconTasks;
use common\models\user\UserChecking;
use common\models\user\UserSearch;
use common\models\user\UserTree;
use Yii;
use yii\filters\AccessControl;
use common\models\user\User;
use backend\components\CrudController;

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
                ],
            ],
        ]);
    }

    protected function _getSearchClassName()
    {
        return UserSearch::class;
    }

    public function actionRevoke($parentId, $userId)
    {
        /** @var UserTree $t */
        $t = UserTree::find()
            ->andWhere(['user_id' => $userId])
            ->one();

        $t->parent_user_id = 509;
        $t->save();

        return $this->redirect(['profile', 'userId' => $parentId]);
    }

    public function actionProfile($userId)
    {
        /** @var User $user */
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }

        $forms = [
            'roleForm' => new RoleForm(),
            'bonusForm' => new BonusForm(),
            'payoutForm' => new PayoutForm(),
            'skinForm' => new SkinForm(),
            'banForm' => new BanForm(),
            'muteForm' => new MuteForm(),
        ];

        foreach ($forms as $form) {
            $form->setUserId($userId);
        }

        $bodyParams = Yii::$app->request->bodyParams;
        $messages = [
            'RoleForm' => 'Роль пользователя успешно изменена!',
            'User' => 'Пользователь успешно изменен!',
            'BonusForm' => 'Бонус успешно начислен!',
            'PayoutForm' => 'Вывод успешно проведен!',
            'SkinForm' => 'Скин успешно отправлен!',
            'BanForm' => 'Бан успешно выдан!',
            'MuteForm' => 'Мут успешно выдан!',
        ];

        foreach ($messages as $formName => $message) {
            if (!empty($bodyParams[$formName])) {
                $form = $formName === 'User' ? $user : $forms[lcfirst($formName)];
                if ($form->load(Yii::$app->request->post()) && 
                    ($formName === 'User' ? $form->save() : $form->saveRecord())) {
                    Yii::$app->session->addFlash('success', $message);
                    return $this->redirect(['profile', 'userId' => $userId]);
                }
            }
        }

        return $this->render('profile', array_merge([
            'user' => $user,
        ], $forms));
    }

    public function actionUnban($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }
        $user->unban();
        Yii::$app->session->addFlash('success', 'Бан успешно снят!');
        return $this->redirect(['profile', 'userId' => $userId]);
    }

    public function actionCheckingStart($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }

        $moder = Yii::$app->user->identity;
        $model = new UserChecking();
        $model->user_id = $user->id;
        $model->status = UserChecking::STATUS_CHECKING;
        $model->checking_by = $moder->id;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save();

        $command = "iqrs call2 \"{$user->steam_id}\" \"{$moder->discord}\"";
        RconTasks::execute($command);

        Yii::$app->session->addFlash('success', 'Игрок вызван на проверку!');
        return $this->redirect(['profile', 'userId' => $userId]);
    }

    public function actionCheckingStop($userId)
    {
        $user = User::findOne($userId);
        if (!$user) {
            throw new \yii\web\NotFoundHttpException('Пользователь не найден');
        }

        /** @var UserChecking $model */
        $model = UserChecking::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['status' => UserChecking::STATUS_CHECKING])
            ->one();

        if ($model) {
            $model->status = UserChecking::STATUS_DONE;
            $model->done_at = date('Y-m-d H:i:s');
            $model->save();

            $command = "iqrs dismiss2 \"{$user->steam_id}\"";
            RconTasks::execute($command);
        }

        Yii::$app->session->addFlash('success', 'Проверка завершена!');
        return $this->redirect(['profile', 'userId' => $userId]);
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

}
