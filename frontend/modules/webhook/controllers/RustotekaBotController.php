<?php

namespace frontend\modules\webhook\controllers;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\components\telegram\foreignSystem\RustotekaBotSystem;
use Yii;
use frontend\modules\webhook\components\IndexController;

class RustotekaBotController extends IndexController
{
    protected function _getSystem()
    {
        return new RustotekaBotSystem();
    }
}