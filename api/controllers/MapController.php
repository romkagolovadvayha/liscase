<?php

namespace api\controllers;

use common\models\box\DropImage;
use common\models\map\Map;
use common\models\map\MapList;
use common\models\mirrors\Mirrors;
use common\models\user\User;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\JsonResponseFormatter;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class MapController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['webhook']
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ]);
    }

    public function actionWebhook()
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        try {
            $data = json_decode(Yii::$app->request->rawBody, 1);
            Yii::$app->telegramChats->sendMessage(Yii::$app->request->rawBody);
            $response = (clone \Yii::$app->curl)
                ->setHeader('X-API-Key', '03f6a4103d7d4820bed03f4322f72f26')
                ->setHeader('accept', 'application/json')
                ->get('https://api.rustmaps.com/v4/maps/' . $data['Id']);

            Yii::$app->telegramChats->sendMessage($response);
            $response = json_decode($response, 1);
            if (!empty($response['data'])) {
                /** @var MapList $model */
                $model = MapList::find()
                    ->andWhere(['hash' => $response['data']['id']])
                    ->one();
                if (!empty($model)) {
//                    $decodedData = file_get_contents($response['data']['downloadUrl']);
//                    $p = explode('/', $response['data']['downloadUrl']);
//                    $newFileName = $p[count($p) - 1];
                    //Yii::$app->s3Api->uploadFile('maps/' . $newFileName, $decodedData);
                    $model->url = $response['data']['downloadUrl'];

                    $fileIconPathFileName = "{$response['data']['id']}.jpg";
                    $filePathFileName = "/uploads/maps/{$response['data']['id']}_200x200.jpg";
                    $filePath = Map::upload($response['data']['imageIconUrl'], $fileIconPathFileName);
                    $fullNewPath150 = \Yii::getAlias('@frontend/web') . $filePathFileName;
                    DropImage::resizeImage($filePath, $fullNewPath150, 200);
                    $model->image = '/uploads/maps/' . $fileIconPathFileName;
                    $model->image_preview = $filePathFileName;

                    $model->save(false);
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("CustomMapGenerateJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }


        return [
            'success' => true
        ];
    }
}