<?php

namespace frontend\modules\api\components;

use Yii;
use yii\web\Controller;
use yii\helpers\Json;

class IndexController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @param mixed $answerData
     */
    protected function _answer($answerData)
    {
        echo Json::encode($answerData);
        Yii::$app->end();
    }
}