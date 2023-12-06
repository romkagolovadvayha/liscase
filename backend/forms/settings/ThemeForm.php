<?php

namespace backend\forms\settings;

use common\components\base\Model;
use common\models\settings\Settings;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class ThemeForm extends Model
{
    public string $body_background;
    public string $background_image;
    public string $a_color;
    public string $header_top_background;
    public string $header_top_color;
    public string $header_background;
    public string $header_color;
    public string $logo_background;
    public string $logo_color;
    public string $footer;
    public string $footer_text;
    public string $button;
    public string $button_text;
    public string $blog_item_data_color;
    public string $blog_item_color;
    public string $blog_item_background;

    public function __construct() {
        parent::__construct();
        foreach ($this->attributes() as $attribute) {
            $this->{$attribute} = Settings::getByKey($attribute);
        }
    }

    public function rules(): array
    {
        return ArrayHelper::merge([
            [$this->attributes(), 'trim'],
            [['background_image'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png'],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'body_background' => 'Цвет фона',
            'background_image' => 'Фон сайта',
            'a_color' => 'Цвет ссылок',
            'header_top_background' => 'Цвет фона верхней части сайта',
            'header_top_color' => 'Цвет текста верхней части сайта',
            'header_background' => "Цвет Header'а",
            'header_color' => "Цвет текста Header'а",
            'logo_background' => "Цвет фона логотипа",
            'logo_color' => "Цвет текста логотипа",
            'footer' => "Цвет фона нижней части сайта",
            'footer_text' => "Цвет текста нижней части сайта",
            'button' => "Цвет фона кнопки",
            'button_text' => "Цвет текста кнопки",
            'blog_item_data_color' => "Цвет дополнительного текста блока сайта",
            'blog_item_color' => "Цвет текста блока сайта",
            'blog_item_background' => "Цвет фона блока сайта",
        ]);
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        foreach ($this->attributes() as $attribute) {
            if (in_array($attribute, ['background_image'])) {
                continue;
            }
            Settings::setByKey($attribute, $this->{$attribute});
        }
        $this->background_image = $this->_loadImage(UploadedFile::getInstance($this, 'background_image'), 'background_image');
        return true;
    }

    private function _loadImage($image, $key) {
        if (empty($image) || empty($image->tempName)) {
            return '';
        }
        $uploadDir = Yii::getAlias('@app/web/uploads');
        $fileUrl = "/theme/$key.png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($image->tempName));
        return $filePath;
    }


}
