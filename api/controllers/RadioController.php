<?php

namespace api\controllers;

use common\models\servers\ServersRadioStation;
use yii\web\Controller;
use Yii;
use yii\web\Response;

class RadioController extends Controller
{

    public function actionList()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        // Получаем радиостанции из базы данных
        $stations = ServersRadioStation::find()
            ->where(['status' => ServersRadioStation::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $list = [];
        foreach ($stations as $station) {
            $item = [
                'name' => $station->name,
                'url' => $station->url,
            ];
            
            // Добавляем логотип, если есть
            if ($station->logo) {
                $item['logo'] = $station->getLogoUrl();
            }
            
            $list[] = $item;
        }

        $str = "";
        foreach ($list as $item) {
            $str .= ',' . $item['name'] . ',' . $item['url'];
            if (isset($item['logo'])) {
                $str .= ',' . $item['logo'];
            } else {
                $str .= ',';
            }
        }

        return [
            'radioList' => substr($str, 1)
        ];
    }

}
