<?php

namespace api\controllers;

use common\models\servers\Servers;
use yii\web\Controller;
use Yii;
use yii\web\Response;

class ServersController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action)
    {
        // Устанавливаем CORS заголовки
        Yii::$app->response->headers->set('Access-Control-Allow-Origin', '*');
        Yii::$app->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        Yii::$app->response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        Yii::$app->response->headers->set('Access-Control-Max-Age', '3600');
        
        // Обработка preflight запросов
        if (Yii::$app->request->method === 'OPTIONS') {
            Yii::$app->response->statusCode = 200;
            Yii::$app->end();
        }
        
        return parent::beforeAction($action);
    }

    public function actionIndex() {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Servers[] $list */
        $list = Servers::find()
                       ->with('serversTags') // Загружаем теги вместе с серверами (eager loading)
                       ->cache(60)
                       ->andWhere(['IN', 'status', [Servers::STATUS_NOACTIVE, Servers::STATUS_ACTIVE]])
                       ->orderBy(['sort' => SORT_ASC])
                       ->all();

        $items = [];
        foreach ($list as $item) {
            // Получаем теги сервера через связь
            $tags = [];
            foreach ($item->serversTags as $serverTag) {
                $tags[] = $serverTag->link_name;
            }
            
            $items[] = [
                'name' => $item->name,
                'ip' => $item->ip,
                'port' => $item->port,
                'query' => $item->query,
                'tag' => $item->tag,
                'online' => (int)$item->players, // Текущий онлайн на сервере
                'joined' => (int)$item->joined, // Игроки в очереди
                'max' => (int)$item->max, // Максимальный онлайн
                'tags' => implode(', ', $tags), // Теги сервера через запятую (link_name)
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
