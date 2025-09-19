<?php

namespace api\controllers;

use yii\web\Controller;
use Yii;
use yii\web\Response;

class RustQuizController extends Controller
{

    public function actionList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $questions = Yii::$app->openAiQuiz->questions(20);

        return [
            'version' => '1.0',
            'locale' => "ru-RU",
            'questions' => $questions,
        ];
    }

}
