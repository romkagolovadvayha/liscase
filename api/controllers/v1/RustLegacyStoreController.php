<?php

namespace api\controllers\v1;

use frontend\controllers\ApiController;
use Yii;
use yii\filters\Cors;
use yii\web\Controller;
use yii\web\Response;

/**
 * Старый query-string API магазина ProstojRUST (?secret=&method=basket&…), как frontend api/index.
 */
class RustLegacyStoreController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'cors' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => ['*'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                ],
            ],
        ]);
    }

    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');

        $req = Yii::$app->request;
        $secret = $req->get('secret');
        $method = $req->get('method');
        $itemId = $req->get('item_id');
        if ($method === 'take' && ($itemId === null || $itemId === '')) {
            $itemId = $req->get('id');
        }

        $api = new ApiController('api', Yii::$app);
        return $api->actionIndex(
            $secret,
            $method,
            $req->get('steam_id'),
            $itemId,
            $req->get('id')
        );
    }
}
