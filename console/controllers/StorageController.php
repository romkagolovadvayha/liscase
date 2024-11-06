<?php

namespace console\controllers;

use Yii;
use common\models\box\Box;
use yii\base\BaseObject;
use yii\console\Controller;

class StorageController extends Controller
{
    /**
     * storage/test
     * @throws \Exception
     */
    public function actionTest()
    {
        $filePatch = Yii::getAlias('@frontend/web/images/logo.png');
        $filename = basename($filePatch);
        $response = Yii::$app->s3Api->uploadFile('support/' . time() . $filename, file_get_contents($filePatch));
        print_r($response);
    }

}
