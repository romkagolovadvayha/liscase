<?php

namespace api\controllers;

use common\models\servers\Servers;
use yii\web\Controller;
use Yii;
use yii\web\Response;

class ServersController extends Controller
{

    public function actionIndex() {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Servers[] $list */
        $list = Servers::find()
                       ->cache(60)
                       ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                       ->orderBy(['sort' => SORT_ASC])
                       ->all();

        $items = [];
        foreach ($list as $item) {
            $items[] = [
                'name' => $item->name,
                'ip' => $item->ip,
                'port' => $item->port,
                'query' => $item->query,
                'tag' => $item->tag,
            ];
        }

        return $items;
    }

    public function actionRules() {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Servers[] $list */
        $list = Servers::find()
                       ->cache(60)
                       ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                       ->orderBy(['sort' => SORT_ASC])
                       ->all();

        $items = [];
        foreach ($list as $item) {
            $items[] = [
                'name' => $item->name,
                'link' => $item->getLink('rules'),
            ];
        }

        return $items;
    }

}
