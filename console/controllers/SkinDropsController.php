<?php

namespace console\controllers;

use common\models\servers\Servers;
use common\models\skindrops\SkindropsLink;
use common\models\stats\Info;
use yii\base\BaseObject;
use yii\console\Controller;

class SkinDropsController extends Controller
{
    /**
     * Получает информацию о серверах
     * skin-drops/parser-links
     *
     * @throws \Exception
     */
    public function actionParserLinks()
    {
        $result = json_decode(file_get_contents(__DIR__ . "/../../skindrops.json"), 1);
        foreach ($result as $steamId => $item) {
            if (!empty($item['tradeurl'])) {
                /** @var SkindropsLink $model */
                $model = SkindropsLink::find()
                    ->andWhere(['steam_id' => $steamId])
                    ->one();
                if (empty($model)) {
                    $model = new SkindropsLink();
                    $model->steam_id = $steamId;
                    $model->tradeurl = $item['tradeurl'];
                    $str = str_replace('https://steamcommunity.com/tradeoffer/new/?', '', $model->tradeurl);
                    $array = explode('&', $str);
                    $model->partner = explode('=', $array[0])[1];
                    $model->token = explode('=', $array[1])[1];
                    $model->save(false);
                } elseif ($model->tradeurl != $item['tradeurl']) {
                    echo $model->tradeurl . PHP_EOL;
                } else {
                    $model->tradeurl = $item['tradeurl'];
                    $model->save();
                }
            }
        }
    }
}
