<?php

namespace common\controllers;

use Yii;
use yii\helpers\Html;
use yii\web\Controller;
use yii\helpers\Url;
use common\components\web\Cookie;

class WebController extends Controller
{
    public $defaultAction = 'index';

    public $metaDescription;
    public $containerClass;
    public $metaImage;

    public $breadCrumbs;
    public $hideTopBanner = false;

    public function beforeAction($action)
    {
        $this->_setRefCookies();
        $this->_setUtmSourceCookies();

        return parent::beforeAction($action);
    }

    public function _setRefCookies()
    {
        $refCode = Yii::$app->request->get('refCode');
        if (empty($refCode)) {
            return;
        }

        Cookie::remove('refCode');
        Cookie::add('refCode', $refCode, true, 365 * 24 * 60);
    }

    public function _setUtmSourceCookies()
    {
        $utmList = [
            'utm_source',
            'utm_post',
            'utm_telegram_id',
        ];

        foreach ($utmList as $utmName) {
            $value = Yii::$app->request->get($utmName);
            if (!empty($value)) {
                Yii::$app->session->setFlash($utmName, $value);
            }
        }
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