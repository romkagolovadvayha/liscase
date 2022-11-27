<?php

namespace common\components\web;

use Yii;
use yii\base\BootstrapInterface;
use common\models\user\User;

class UserParam implements BootstrapInterface
{
    public function bootstrap($app)
    {
        if (Yii::$app->user->isGuest) {
            return true;
        }

        $requestUrl = Yii::$app->request->url;

        $user = Yii::$app->user->identity;

        if ($user->status == User::STATUS_ACTIVE && ($user instanceof User && $user->is_tmp_blocked == 1)) {
            if (strstr($requestUrl, '/cabinet/profile')
                || strstr($requestUrl, '/auth/two-step-scan')
                || strstr($requestUrl, '/auth/disable-two-step-auth')
            ) {
                return true;
            }

            return Yii::$app->response->redirect('/cabinet/profile/tmp-blocked');
        }

        $isAdminUser = Cookie::getValue('fromSwitcherUserId');
        if (!empty($isAdminUser)) {
            return true;
        }

        if (strstr(Yii::$app->request->absoluteUrl, '1defi.fund')) {
            $is1DefiAdminUser = Cookie::getValue('fromSwitcher1DefiUserId');
            if (!empty($is1DefiAdminUser)) {
                return true;
            }
        }

        if (strstr($requestUrl, '/cabinet/profile')
            || strstr($requestUrl, '/auth/two-step-scan')
            || strstr($requestUrl, '/auth/disable-two-step-auth')
        ) {
            return true;
        }

        if ($user->two_step_auth && !Yii::$app->session->get('verify_two_step_authenticator')) {
            return Yii::$app->response->redirect([
                '/auth/two-step-scan',
                'from' => (strstr(Yii::$app->request->referrer, 'cabinet/profile/two-step-auth') ? 1 : 0),
            ]);
        }

        return true;
    }
}