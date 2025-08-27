<?php

namespace frontend\forms\serverskin;

use common\models\box\BoxImage;
use common\models\box\DropImage;
use common\models\building\BuildingResident;
use common\models\servers\Servers;
use common\models\serverskin\ServerSkin;
use common\models\user\User;
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

        $approvedSkins = explode(',', Yii::$app->settings->get('custom-skins_approved_list'));
        if (in_array($skinId, $approvedSkins)) {
            $this->addError('steam_link', Yii::t('common', 'Добавление запрещено: работа одобрена разработчиками и доступна в магазине'));
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


        $info = ServerSkin::getInfoSkin($skinId);
        $preview = $info['preview_url'];
        $title = $info['title'];
        $creatorSteamId = $info['creator'];
        $creatorUser = User::findBySteamId($creatorSteamId);

        $imagePath = $this->_loadImage(file_get_contents($preview));
        $this->name = $title;
        $this->skin_id = $skinId;
        $this->image = $imagePath;
        $this->category = $info['tags'][1]['tag'];
        $this->creator_user_id = $creatorUser->id;
        $this->save();
        Yii::$app->telegramSupport->sendMessage(
            "👕 Новый скин отправлен на модерацию!",
            [
                [
                    'text' => '🟢 Принять',
                    'callback_data' => json_encode([
                                                       'action'   => 'success-skin',
                                                       'skin_id'  => $skinId,
                                                   ])
                ],
                [
                    'text' => '🔴 Отклонить',
                    'callback_data' => json_encode([
                                                       'action'   => 'reject-skin',
                                                       'skin_id'  => $skinId,
                                                   ])
                ]
            ],
            $this->getImagePubUrl() // вот сюда картинка
        );
        Yii::$app->personalBotTelegram->sendMessage(Yii::$app->user->identity->telegram_chat_id, "👕 Скин отправлен на модерацию!");
        return true;
    }


    private function _loadImage($image) {
        if (empty($image)) {
            return null;
        }
        $uploadDir = Yii::getAlias('@app/web');
        $filename = $this->id . "_" . md5(time()) . ".png";
        $fileUrl = "/uploads/server-skin/{$filename}";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, $image);

        $newPath150 = "/uploads/server-skin-x150/" . $filename;
        $fullNewPath150 = \Yii::getAlias('@frontend/web') . $newPath150;
        $newPath64 = "/server-skin-64/" . $filename;
        $fullNewPath64 = \Yii::getAlias('@frontend/web/uploads') . $newPath64;
        $newPath150 = "/server-skin-150/" . $filename;
        $fullNewPath150 = \Yii::getAlias('@frontend/web/uploads') . $newPath150;
        if (file_exists($filePath)) {
            DropImage::resizeImage($filePath, $fullNewPath150, 200);
            DropImage::resizeImage($filePath, $fullNewPath64, 64);
            DropImage::resizeImage($filePath, $fullNewPath150, 150);
            $this->image_64 = $newPath64;
            $this->image_150 = $newPath150;
            $this->save(false);
        }
        return $newPath150;
    }
}
