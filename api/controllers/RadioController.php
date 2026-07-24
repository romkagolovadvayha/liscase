<?php

namespace api\controllers;

use common\models\servers\ServersRadioStation;
use yii\web\Controller;
use Yii;
use yii\web\Response;
use yii\web\NotFoundHttpException;

class RadioController extends Controller
{

    public function beforeAction($action)
    {
        if (!Yii::$app->settings->get('section_radio')) {
            throw new NotFoundHttpException('Страница не найдена');
        }
        return parent::beforeAction($action);
    }

    public function actionList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Получаем радиостанции из базы данных
        $stations = ServersRadioStation::find()
            ->where(['status' => ServersRadioStation::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $str = "";
        foreach ($stations as $station) {
            $str .= ',' . $station->name . ',' . $station->url;
        }

        return [
            'radioList' => substr($str, 1)
        ];
    }

}
