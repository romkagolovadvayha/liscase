<?php

namespace console\controllers;

use common\models\box\Box;
use common\models\box\DropImage;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use yii\console\Controller;

class BoxController extends Controller
{
    /**
     * Генерируем рандомный дроп, для рандомных автоматически сгенерированных юзеров
     * сделано для эмитации активности на сайте
     * box/generate-user-drop
     *
     * @throws \Exception
     */
    public function actionGenerateUserDrop()
    {
        UserBox::botGenerate();
    }

    /**
     * box/generate-image-64
     */
    public function actionGenerateImage64()
    {
        /** @var DropImage[] $images */
        $images = DropImage::find()
            ->andWhere(['type' => DropImage::TYPE_ORIG])
            ->all();

        foreach ($images as $image) {
            $ex = explode('/', $image->image);
            $filename = $ex[count($ex) - 1];
            $path = \Yii::getAlias('@frontend/web/uploads') . $image->image;
            $newPath150 = "/drop150/" . $filename;
            $fullNewPath150 = \Yii::getAlias('@frontend/web/uploads') . $newPath150;
            $newPath64 = "/drop64/" . $filename;
            $fullNewPath64 = \Yii::getAlias('@frontend/web/uploads') . $newPath64;
            $newPath100 = "/drop100/" . $filename;
            $fullNewPath100 = \Yii::getAlias('@frontend/web/uploads') . $newPath100;
            if (file_exists($path)) {
                DropImage::resizeImage($path, $fullNewPath150, 150);
                DropImage::createRecord($newPath150, DropImage::TYPE_150, $image->drop_id);
                DropImage::resizeImage($path, $fullNewPath64, 64);
                DropImage::createRecord($newPath64, DropImage::TYPE_64, $image->drop_id);
//                DropImage::resizeImage($path, $fullNewPath100, 100);
//                DropImage::createRecord($newPath100, DropImage::TYPE_100, $image->drop_id);
            }
        }

    }


}
