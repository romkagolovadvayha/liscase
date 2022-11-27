<?php

namespace common\components\web;

use Yii;

class LanguagePickerComponent extends \lajax\languagepicker\Component
{
    public function init()
    {
        $this->callback = function () {
            if (!Yii::$app->user->isGuest) {
                Yii::$app->user->identity->updateCurrentLanguage();
            }
        };

        parent::init();
    }
}