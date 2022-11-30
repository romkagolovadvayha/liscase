<?php

namespace console\controllers;

use common\models\box\Box;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use yii\console\Controller;

class BoxController extends Controller
{
    /**
     * Генерируем рандомный дроп, для рандомных автоматически сгенерированных юзеров
     * сделано для эмитации активности на сайте
     * box/generate-user-drop
     *
     * @throws \Exception
     */
    public function actionGenerateUserDrop()
    {
        UserBox::botGenerate();
    }

}
