<?php

namespace api\controllers;

use common\components\queue\process\QuizGetListJob;
use yii\base\BaseObject;
use yii\web\Controller;
use Yii;
use yii\web\Response;

class RustQuizController extends Controller
{

    public function actionList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $questions = [];
        $cacheKey = 'quiz_list';
        if (!empty(Yii::$app->cache->get($cacheKey))) {
            $questions = Yii::$app->cache->get($cacheKey);
        }

        Yii::$app->queueProcess->push(new QuizGetListJob(['count' => 20]));
        return [
            'version' => '1.0',
            'locale' => "ru-RU",
            'questions' => $questions,
        ];
    }

}
