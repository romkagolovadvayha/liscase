<?php

namespace frontend\components\menu;

use Yii;
use yii\helpers\Html;
use common\components\menu\BaseNavMenu;

class PromotionNavMenu extends BaseNavMenu
{
    /**
     * @return array
     */
    protected function _getItemsArray()
    {
        $user = Yii::$app->user->identity;

        return [
            [
                'label'  => Yii::t('common', 'Актуальные задания'),
                'url'    => '/tasks',
                'active' => $this->_checkActive('cabinet/promotion/index'),
            ],
            [
                'label'  => Yii::t('common', 'Выполненные задания'),
                'url'    => '/tasks/done',
                'active' => $this->_checkActive('cabinet/promotion/done'),
            ],
        ];
    }
}