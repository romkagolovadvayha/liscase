<?php

namespace common\controllers;

use common\components\helpers\Role;
use common\models\user\User;
use Yii;
use yii\helpers\Html;
use yii\web\Controller;
use yii\helpers\Url;
use common\components\web\Cookie;
use yii\web\ForbiddenHttpException;

class WebController extends Controller
{
    public $defaultAction = 'index';

    public function beforeAction($action)
    {
        // Получаем текущего пользователя
        $user = Yii::$app->user->identity;
        // Проверяем, заблокирован ли пользователь
        if (!Yii::$app->user->isGuest && $user->status === User::STATUS_BLOCKED) {
            Yii::$app->response->redirect(['/blocked'])->send();
            return false; // Останавливаем выполнение текущего действия
        }

        if (Yii::$app->settings->get('site_enabled')) {
            if (Yii::$app->user->isGuest || !Yii::$app->user->identity->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
                Yii::$app->response->redirect(['/worked'])->send();
                return false; // Останавливаем выполнение текущего действия
            }
        }

        $this->_setRefCookies();

        return parent::beforeAction($action);
    }

    public function _setRefCookies()
    {
        $refCode = Yii::$app->request->get('refCode');
        if (empty($refCode)) {
            return;
        }

        if (empty(Cookie::getValue('refCode'))) {
            $user = User::findByRefCode($refCode);
            if (!empty($user)) {
                $user->userProfile->referral_click++;
                $user->userProfile->save();
            }
        }
        Cookie::remove('refCode');
        Cookie::add('refCode', $refCode, true, 365 * 24 * 60);
    }

    /**
     * @param string|null $url
     */
    protected function _rememberIndexUrl($url = null)
    {
        if (empty($url)) {
            $url = Yii::$app->request->url;
        }

        Url::remember($url, $this->getUniqueId());
    }

    public function getIndexUrl()
    {
        $redirectUrl = Url::previous($this->getUniqueId());
        if (empty($redirectUrl)) {
            $redirectUrl = ['index'];
        }

        return $redirectUrl;
    }

    /**
     * @param string $submitTitle
     * @param bool   $showCancel
     * @param bool   $submitConfirm
     *
     * @return string
     */
    public function getFormButtons($submitTitle = null, $showCancel = true, $submitConfirm = false)
    {
        $html = Html::beginTag('div', ['class' => 'form-group']);

        if (empty($submitTitle)) {
            $submitTitle = Yii::t('common', 'Сохранить');
        }

        $submitOptions = [
            'class' => 'btn btn-primary',
        ];

        if ($submitConfirm) {
            $submitOptions['data-confirm'] = Yii::t('common', 'Вы уверены, что хотите выполнить эту операцию?');
        }

        $html .= Html::submitButton($submitTitle, $submitOptions);

        if ($showCancel) {
            $html .= '&nbsp;' . Html::a(Yii::t('common', 'Отмена'), $this->getIndexUrl(),
                    ['class' => 'btn btn-default']);
        }

        $html .= Html::endTag('div');

        return $html;
    }
}