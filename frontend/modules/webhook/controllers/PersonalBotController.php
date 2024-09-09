<?php

namespace frontend\modules\webhook\controllers;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use Yii;
use frontend\modules\webhook\components\IndexController;

class PersonalBotController extends IndexController
{
    protected function _getSystem()
    {
        return new PersonalBotSystem();
    }
}