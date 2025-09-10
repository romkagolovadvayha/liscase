<?php

namespace common\controllers;

use Yii;
use yii\web\Controller;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\Response;
use common\models\user\User;
use common\components\helpers\Role;
use common\components\web\Cookie;

class WebController extends Controller
{
    public $defaultAction = 'index';

    /**
     * Флаг принудительного режима рендера:
     * null  — авто (AJAX/PJAX → ajax render, иначе обычный)
     * true  — принудительно ajax-поведение
     * false — принудительно обычный render с лэйаутом
     */
    protected ?bool $forceAjaxMode = null;

    /** Удобные переключатели (можно вызывать внутри экшена при особых кейсах) */
    protected function forceAjax(): void { $this->forceAjaxMode = true; }
    protected function forceFull(): void { $this->forceAjaxMode = false; }
    protected function forceAuto(): void { $this->forceAjaxMode = null; }

    /** Определение «ajax-подобного» запроса (AJAX или PJAX) */
    protected function isAjaxLike(): bool
    {
        $req = Yii::$app->request;
        if ($req->isAjax) {
            return true;
        }
        return (bool)$req->headers->get('X-PJAX'); // PJAX тоже считаем ajax
    }

    /* ===================== Переопределённые render-методы ===================== */

    /**
     * Универсальный рендер: для AJAX/PJAX вернёт ajax-рендер (без лэйаута),
     * для обычного — классический рендер с лэйаутом.
     */
    public function render($view, $params = [])
    {
        $ajax = $this->forceAjaxMode !== null ? $this->forceAjaxMode : $this->isAjaxLike();
        if ($ajax) {
            // важно: используем parent::renderAjax(), чтобы корректно
            // подмешались зарегистрированные скрипты/стили для ajax
            return parent::renderAjax($view, $params);
        }
        return parent::render($view, $params);
    }

    /**
     * Совместимость: если кто-то явно вызывает renderAjax(),
     * то при не-AJAX запросе отдадим полноразмерный рендер (с лэйаутом),
     * чтобы не требовались двойные return в экшенах.
     */
    public function renderAjax($view, $params = [])
    {
        $ajax = $this->forceAjaxMode !== null ? $this->forceAjaxMode : $this->isAjaxLike();
        if ($ajax) {
            return parent::renderAjax($view, $params);
        }
        return parent::render($view, $params);
    }

    /* ===================== Твой beforeAction и помощники ===================== */

    public function beforeAction($action)
    {
        // Блокировка аккаунта
        $user = Yii::$app->user->identity;
        if (!Yii::$app->user->isGuest && $user && $user->status === User::STATUS_BLOCKED) {
            Yii::$app->response->redirect(['/blocked'])->send();
            return false;
        }

        // Режим "site_enabled" → только админы/модераторы
        if (Yii::$app->settings->get('site_enabled')) {
            if (Yii::$app->user->isGuest || !Yii::$app->user->identity->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
                Yii::$app->response->redirect(['/worked'])->send();
                return false;
            }
        }

        $this->_setRefCookies();

        if ($this->id == 'clans' && $action->id == 'upload') {
            $this->enableCsrfValidation = false;
        }

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

    protected function rememberIndexUrl($url = null)
    {
        if (empty($url)) {
            $url = Yii::$app->request->url;
        }
        Url::remember($url, $this->getUniqueId());
    }

    public function getIndexUrl()
    {
        $redirectUrl = Url::previous($this->getUniqueId());
        return $redirectUrl ?: ['index'];
    }

    public function getFormButtons($submitTitle = null, $showCancel = true, $submitConfirm = false)
    {
        $html = Html::beginTag('div', ['class' => 'form-group']);

        if (empty($submitTitle)) {
            $submitTitle = Yii::t('common', 'Сохранить');
        }

        $submitOptions = ['class' => 'btn btn-primary'];
        if ($submitConfirm) {
            $submitOptions['data-confirm'] = Yii::t('common', 'Вы уверены, что хотите выполнить эту операцию?');
        }

        $html .= Html::submitButton($submitTitle, $submitOptions);

        if ($showCancel) {
            $html .= '&nbsp;' . Html::a(Yii::t('common', 'Отмена'), $this->getIndexUrl(), ['class' => 'btn btn-default']);
        }

        $html .= Html::endTag('div');
        return $html;
    }
}
