<?php

namespace frontend\forms\buildings;

use common\models\box\BoxImage;
use common\models\building\BuildingResident;
use common\models\servers\Servers;
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

class BuildingForm extends Building
{
    public $image;
    public $residents;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['image', 'residents'], 'required'],
            [['image'], 'safe'],
            [['image'], 'file', 'skipOnEmpty' => true, 'extensions' => ['svg', 'jpg', 'jpeg', 'gif', 'png'], 'checkExtensionByMimeType' => false, 'maxFiles' => 5],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'image' => Yii::t('common', 'Фотографии постройки'),
            'residents' => Yii::t('common', 'Жильцы'),
            'server_tag' => Yii::t('common', 'Выберите сервер'),
        ]);
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        /** @var Servers $server */
        $server = Servers::find()
            ->andWhere(['tag' => $this->server_tag])
            ->one();
        if (empty($server)) {
            throw new \Exception(Yii::t('common', 'Произошла ошибка при сохрании, напишите пожалуйста администратору сайта'));
        }

        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');

        $this->user_id = Yii::$app->user->id;
        $this->status = Building::STATUS_WAIT;
        $this->wipe = $wipeDate;
        $this->created_at = date('Y-m-d H:i:s');

        if (!$this->validate()) {
            return false;
        }

        if (!in_array($this->user_id, $this->residents)) {
            $this->residents[] = $this->user_id;
        }

        if (!empty($server->team_limit) && $server->team_limit < count($this->residents)) {
            $this->addError('residents', Yii::t('common', 'Нарушение лимита команды, этот сервер для {max_count} человек!', [
                'max_count' => $server->team_limit
            ]));
            return false;
        }

        $validator = new ImageValidator([
                                            'mimeTypes'   => [
                                                'image/jpeg',
                                                'image/png',
                                                'image/gif'
                                            ],
                                            'maxWidth'    => 6000,
                                            'maxHeight'   => 6000,
                                            'skipOnEmpty' => true,
                                        ]);
        foreach (UploadedFile::getInstances($this, 'image') as $img) {
            if (!$validator->validate($img, $error)) {
                $this->addError('image', Yii::t('common', 'Разрешено загружать только файлы PNG, JPEG, GIF'));
                return false;
            }
        }

        if (!$this->save()) {
            throw new \Exception(Yii::t('common', 'Произошла ошибка при сохрании, напишите пожалуйста администратору сайта'));
        }

        $resedent = new BuildingResident();
        $resedent->user_id = $this->user_id;
        $resedent->building_id = $this->id;
        $resedent->save();
        if (empty($server->team_limit) || $server->team_limit > 1) {
            foreach ($this->residents as $userId) {
                if ($this->user_id == $userId) {
                    continue;
                }
                $resedent = new BuildingResident();
                $resedent->user_id = $userId;
                $resedent->building_id = $this->id;
                $resedent->save();
            }
        }

        $this->_loadImages(UploadedFile::getInstances($this, 'image'), $this->id);
        Yii::$app->telegramChats->sendMessage("🏠 Новая постройка отправлена на модерацию!");
        return true;
    }

    private function _loadImages($images, $id) {
        if (empty($images)) {
            return null;
        }
        $s3Api = Yii::$app->s3Api;
        
        foreach ($images as $i => $image) {
            if (empty($image->tempName)) {
                continue;
            }
            
            // Создаем временные файлы для обработки
            $tempDir = sys_get_temp_dir();
            $tempOriginal = $tempDir . '/' . uniqid('building_orig_') . '.png';
            $tempPreview = $tempDir . '/' . uniqid('building_preview_') . '.png';
            
            $fileName = "" . $this->id . "_" . md5(time() . $i) . ".png";
            
            // Сохраняем оригинал во временный файл
            file_put_contents($tempOriginal, file_get_contents($image->tempName));
            
            // Создаем превью
            $imagine = Image::getImagine();
            $img = $imagine->open($tempOriginal);
            $diffWidth = 1;
            $diffHeight = 1;
            $offestX = 0;
            $offestY = 0;
            $newWidth = 200;
            $newHeight = 200;
            if ($img->getSize()->getWidth() > $img->getSize()->getHeight()) {
                $diffWidth = $img->getSize()->getWidth() / $img->getSize()->getHeight();
                $newWidth = 200 * $diffWidth;
                $offestX = $newWidth / 2 - 100;
            } else {
                $diffHeight = $img->getSize()->getHeight() / $img->getSize()->getWidth();
                $newHeight = 200 * $diffHeight;
                $offestY = $newHeight / 2 - 100;
            }

            if ($offestY < 0) {
                $offestY = 0;
            }
            if ($offestX < 0) {
                $offestX = 0;
            }

            $img
                ->resize(new Box($newWidth, $newHeight))
                ->crop(new Point($offestX, $offestY), new Box(200, 200))
                ->save($tempPreview, ['quality' => 70]);
            
            // Загружаем оригинал в S3
            $s3KeyOriginal = 'uploads/buildings/' . $fileName;
            $originalContent = file_get_contents($tempOriginal);
            $s3ResultOriginal = $s3Api->putFile($s3KeyOriginal, $originalContent, 'image/png');
            
            // Загружаем превью в S3
            $s3KeyPreview = 'uploads/buildings/preview_' . $fileName;
            $previewContent = file_get_contents($tempPreview);
            $s3ResultPreview = $s3Api->putFile($s3KeyPreview, $previewContent, 'image/png');
            
            // Удаляем временные файлы
            @unlink($tempOriginal);
            @unlink($tempPreview);
            
            if ($s3ResultOriginal === false || $s3ResultPreview === false) {
                Yii::error('Error uploading building image to S3', __METHOD__);
                continue;
            }
            
            $model = new BuildingImage();
            $model->building_id = $id;
            $model->image = $fileName;
            $model->created_at = date(('Y-m-d H:i:s'));
            $model->save();

            if ($i >= 3) {
                break;
            }
        }
    }

    public function getUrl() {
        return '/buildings/view?id=' . $this->id;
    }
}
