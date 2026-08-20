<?php

namespace api\controllers\v1;

use common\components\rust\RustPluginChatJsonBuilder;
use Yii;
use yii\filters\Cors;
use yii\web\Controller;
use yii\web\Response;

/**
 * Плоский JSON для WipeCommand / WelcomeMessages / HelpText (как на сайте, без обёртки v1).
 */
class RustPluginChatController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'cors' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => ['*'],
                    'Access-Control-Request-Method' => ['GET', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                ],
            ],
        ]);
    }

    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        return parent::beforeAction($action);
    }

    public function actionWipe($serverTag)
    {
        return RustPluginChatJsonBuilder::wipeInfo($serverTag);
    }

    public function actionWelcome($serverTag)
    {
        return RustPluginChatJsonBuilder::welcomeMessage($serverTag);
    }

    public function actionHelp($serverTag)
    {
        return RustPluginChatJsonBuilder::helpInfo($serverTag);
    }
}
