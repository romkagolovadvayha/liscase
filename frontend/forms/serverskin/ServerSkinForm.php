<?php

namespace frontend\forms\serverskin;

use common\models\box\BoxImage;
use common\models\box\DropImage;
use common\models\building\BuildingResident;
use common\models\servers\Servers;
use common\models\serverskin\ServerSkin;
use common\models\serverskin\ServerSkinCategory;
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

        // Загружаем изображение превью с таймаутом
        $context = stream_context_create([
            'http' => [
                'timeout' => 10, // 10 секунд таймаут
                'ignore_errors' => true,
            ]
        ]);
        $imageData = @file_get_contents($preview, false, $context);
        if ($imageData === false || empty($imageData)) {
            $this->addError('steam_link', Yii::t('common', 'Не удалось загрузить изображение превью скина'));
            return false;
        }
        
        // Проверяем размер файла (максимум 10MB)
        if (strlen($imageData) > 10 * 1024 * 1024) {
            $this->addError('steam_link', Yii::t('common', 'Изображение слишком большое (максимум 10MB)'));
            return false;
        }

        $imagePath = $this->_loadImage($imageData);
        if (empty($imagePath)) {
            $this->addError('steam_link', Yii::t('common', 'Ошибка обработки изображения превью'));
            return false;
        }
        $this->name = $title;
        $this->skin_id = $skinId;
        $this->image = $imagePath;
        $tag = $info['tags'][0]['tag'];
        if ($tag == 'Version3') {
            $tag = $info['tags'][1]['tag'];
            if ($tag == 'Skin') {
                $tag = $info['tags'][2]['tag'];
            }
        } elseif ($tag == 'Skin') {
            $tag = $info['tags'][1]['tag'];
            if ($tag == 'Version3') {
                $tag = $info['tags'][2]['tag'];
            }
        }
        $category = ServerSkinCategory::getCategory($tag);
        $this->server_skin_category_id = $category->id;
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
        
        $s3Api = Yii::$app->s3Api;
        $tempDir = sys_get_temp_dir();
        
        // Проверяем, что временная директория существует и доступна для записи
        if (!is_dir($tempDir) || !is_writable($tempDir)) {
            Yii::error('Temporary directory is not writable: ' . $tempDir, __METHOD__);
            return null;
        }
        
        $filename = $this->id . "_" . md5(time()) . ".png";
        
        // Определяем формат изображения по содержимому
        $imageInfo = @getimagesizefromstring($image);
        if ($imageInfo === false) {
            Yii::error('Invalid image data provided to _loadImage', __METHOD__);
            return null;
        }
        
        // Быстрая проверка размеров изображения (максимум 10000x10000)
        if (isset($imageInfo[0]) && isset($imageInfo[1])) {
            if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
                Yii::error('Image dimensions too large: ' . $imageInfo[0] . 'x' . $imageInfo[1], __METHOD__);
                return null;
            }
            if ($imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
                Yii::error('Invalid image dimensions: ' . $imageInfo[0] . 'x' . $imageInfo[1], __METHOD__);
                return null;
            }
        }
        
        // Определяем расширение на основе MIME типа
        $extension = 'png';
        if (!empty($imageInfo['mime'])) {
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            if (isset($mimeToExt[$imageInfo['mime']])) {
                $extension = $mimeToExt[$imageInfo['mime']];
            }
        }
        
        // Сохраняем оригинал во временный файл с правильным расширением
        $tempOriginal = $tempDir . '/' . uniqid('skin_orig_') . '.' . $extension;
        $bytesWritten = file_put_contents($tempOriginal, $image);
        
        // Проверяем, что файл был успешно записан и существует
        if ($bytesWritten === false || !file_exists($tempOriginal) || !is_readable($tempOriginal)) {
            Yii::error('Failed to write temporary image file: ' . $tempOriginal . ' (bytes written: ' . ($bytesWritten !== false ? $bytesWritten : 'false') . ')', __METHOD__);
            return null;
        }
        
        // Проверяем, что это валидное изображение
        $imageSize = @getimagesize($tempOriginal);
        if ($imageSize === false) {
            Yii::error('Invalid image data in temporary file: ' . $tempOriginal, __METHOD__);
            @unlink($tempOriginal);
            return null;
        }
        
        // Быстрая проверка размера файла на диске
        $fileSize = filesize($tempOriginal);
        if ($fileSize === false || $fileSize > 10 * 1024 * 1024) {
            Yii::error('Image file too large: ' . $fileSize . ' bytes', __METHOD__);
            @unlink($tempOriginal);
            return null;
        }
        
        // Создаем превью разных размеров во временных файлах
        $temp200 = $tempDir . '/' . uniqid('skin_200_') . '.png';
        $temp64 = $tempDir . '/' . uniqid('skin_64_') . '.png';
        $temp150 = $tempDir . '/' . uniqid('skin_150_') . '.png';
        
        // Создаем ресайзы по одному, сразу проверяя результат для быстрого возврата ошибки
        $resize200 = DropImage::resizeImage($tempOriginal, $temp200, 200);
        if (!$resize200 || !file_exists($temp200)) {
            Yii::error('Failed to create 200px resize for server skin. Original exists: ' . (file_exists($tempOriginal) ? 'yes' : 'no') . ', Resize result: ' . ($resize200 ? 'true' : 'false') . ', File exists: ' . (file_exists($temp200) ? 'yes' : 'no'), __METHOD__);
            @unlink($tempOriginal);
            return null;
        }
        
        $resize64 = DropImage::resizeImage($tempOriginal, $temp64, 64);
        if (!$resize64 || !file_exists($temp64)) {
            Yii::error('Failed to create 64px resize for server skin. Original exists: ' . (file_exists($tempOriginal) ? 'yes' : 'no') . ', Resize result: ' . ($resize64 ? 'true' : 'false') . ', File exists: ' . (file_exists($temp64) ? 'yes' : 'no'), __METHOD__);
            @unlink($tempOriginal);
            @unlink($temp200);
            return null;
        }
        
        $resize150 = DropImage::resizeImage($tempOriginal, $temp150, 150);
        if (!$resize150 || !file_exists($temp150)) {
            Yii::error('Failed to create 150px resize for server skin. Original exists: ' . (file_exists($tempOriginal) ? 'yes' : 'no') . ', Resize result: ' . ($resize150 ? 'true' : 'false') . ', File exists: ' . (file_exists($temp150) ? 'yes' : 'no'), __METHOD__);
            @unlink($tempOriginal);
            @unlink($temp200);
            @unlink($temp64);
            return null;
        }
        
        // Загружаем все версии в S3
        $s3KeyOriginal = 'uploads/server-skin/' . $filename;
        $s3Key200 = 'uploads/server-skin-x150/' . $filename;
        $s3Key64 = 'uploads/server-skin-64/' . $filename;
        $s3Key150 = 'uploads/server-skin-150/' . $filename;
        
        $s3ResultOriginal = $s3Api->putFile($s3KeyOriginal, file_get_contents($tempOriginal), 'image/png');
        $s3Result200 = $s3Api->putFile($s3Key200, file_get_contents($temp200), 'image/png');
        $s3Result64 = $s3Api->putFile($s3Key64, file_get_contents($temp64), 'image/png');
        $s3Result150 = $s3Api->putFile($s3Key150, file_get_contents($temp150), 'image/png');
        
        // Удаляем временные файлы
        @unlink($tempOriginal);
        @unlink($temp200);
        @unlink($temp64);
        @unlink($temp150);
        
        if ($s3ResultOriginal === false || $s3Result200 === false || $s3Result64 === false || $s3Result150 === false) {
            Yii::error('Error uploading server skin image to S3', __METHOD__);
            return null;
        }
        
        // Сохраняем пути в модели
        $this->image_64 = '/server-skin-64/' . $filename;
        $this->image_150 = '/server-skin-150/' . $filename;
        
        return '/uploads/server-skin-x150/' . $filename;
    }
}
