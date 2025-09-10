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
        foreach ($images as $i => $image) {
            if (empty($image->tempName)) {
                continue;
            }
            $uploadDir = Yii::getAlias('@app/web/uploads');
            $fileName = "" . $this->id . "_" . md5(time() . $i) . ".png";
            $filePath = $uploadDir . "/buildings/" . $fileName;
            $filePathPreview = $uploadDir . "/buildings/preview_" . $fileName;
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath));
                chmod(dirname($filePath), 0777);
            }
            file_put_contents($filePath, file_get_contents($image->tempName));
            $model = new BuildingImage();
            $model->building_id = $id;
            $model->image = $fileName;
            $model->created_at = date(('Y-m-d H:i:s'));
            $model->save();

            $imagine = Image::getImagine();
            $image = $imagine->open($filePath);
            $diffWidth = 1;
            $diffHeight = 1;
            $offestX = 0;
            $offestY = 0;
            $newWidth = 200;
            $newHeight = 200;
            if ($image->getSize()->getWidth() > $image->getSize()->getHeight()) {
                $diffWidth = $image->getSize()->getWidth() / $image->getSize()->getHeight();
                $newWidth = 200 * $diffWidth;
                $offestX = $newWidth / 2 - 100;
            } else {
                $diffHeight = $image->getSize()->getHeight() / $image->getSize()->getWidth();
                $newHeight = 200 * $diffHeight;
                $offestY = $newHeight / 2 - 100;
            }

            if ($offestY < 0) {
                $offestY = 0;
            }
            if ($offestX < 0) {
                $offestX = 0;
            }

            $image
                ->resize(new Box($newWidth, $newHeight))
                ->crop(new Point($offestX, $offestY), new Box(200, 200))
                ->save($filePathPreview, ['quality' => 70]);

            if ($i >= 3) {
                break;
            }
        }
    }

    public function getUrl() {
        return '/buildings/view?id=' . $this->id;
    }
}
