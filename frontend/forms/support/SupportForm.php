<?php

namespace frontend\forms\support;

use common\models\box\BoxImage;
use common\models\building\BuildingResident;
use common\models\servers\Servers;
use common\models\support\Support;
use Imagine\Image\Box;
use common\models\building\Building;
use common\models\building\BuildingImage;
use Imagine\Image\Point;
use React\Socket\Server;
use Yii;
use yii\base\BaseObject;
use yii\imagine\Image;
use yii\helpers\ArrayHelper;
use yii\validators\ImageValidator;
use yii\web\UploadedFile;

class SupportForm extends Support
{

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        $this->user_id = Yii::$app->user->id;
        $this->status = Support::STATUS_OPEN;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->created_at = date('Y-m-d H:i:s');

        if (!$this->validate()) {
            return false;
        }

        if (!$this->save()) {
            throw new \Exception(Yii::t('common', 'Произошла ошибка при сохрании, напишите пожалуйста администратору сайта'));
        }

        //Yii::$app->telegramChats->sendMessage("🎫 Создан новый тикет!");
        return true;
    }

}
