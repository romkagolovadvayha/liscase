<?php

namespace frontend\forms\serverskin;

use common\models\box\BoxImage;
use common\models\building\BuildingResident;
use common\models\servers\Servers;
use common\models\serverskin\ServerSkin;
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

class ServerSkinForm extends ServerSkin
{
    public $steam_link;

    public function rules(): array
    {
        return [
            [['steam_link'], 'required'],
            [['steam_link'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'steam_link' => Yii::t('common', 'Ссылка на скин в мастерской Steam'),
        ]);
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        $this->user_id = Yii::$app->user->id;
        $this->status = ServerSkin::STATUS_WAIT;
        $this->created_at = date('Y-m-d H:i:s');

        if (!$this->validate()) {
            return false;
        }

        $skinId = preg_replace("/[^0-9]/", '', $this->steam_link);
        if (empty($skinId) || strlen($skinId) !== 10) {
            $this->addError('steam_link', Yii::t('common', 'Неверно указана ссылка на работу в мастерской Steam'));
            return false;
        }

        $exists = ServerSkin::find()
            ->andWhere(['skin_id' => $skinId])
            ->andWhere(['IN', 'status', [ServerSkin::STATUS_ACTIVE, ServerSkin::STATUS_WAIT]])
            ->exists();

        if ($exists) {
            $this->addError('steam_link', Yii::t('common', 'Данная работа уже добавлена или ожидает модерации'));
            return false;
        }

//      $fileName = $this->_loadImages($imageLink, $this->id);
//        Yii::$app->telegramChats->sendMessage("👕 Новый скин отправлен на модерацию!");
        return true;
    }

    private function _loadImages($imageLink) {
        $uploadDir = Yii::getAlias('@app/web/uploads');
        $fileName = "" . $this->id . "_" . md5(time()) . ".png";
        $filePath = $uploadDir . "/custom-skins/" . $fileName;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageLink));
        return $fileName;
    }
}
