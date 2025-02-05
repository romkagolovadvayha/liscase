<?php
namespace common\models\site;

use Yii;
use yii\base\Model;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * This is the model class for table "servers".
 *
 * @property int    $id
 * @property string $name
 * @property string $category
 * @property string $type
 * @property string $value
 * @property string $code
 */
class SiteSetting extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%site_settings}}';
    }

    public function rules()
    {
        return [
            [['name', 'category', 'type'], 'required'],
            [['name', 'category'], 'string', 'max' => 255],
            [['type'], 'in', 'range' => ['text', 'color', 'image', 'number', 'checkbox', 'longtext']],
            [['value'], 'string'],
            [['code'], 'string', 'max' => 255],
        ];
    }

    // Логика для обработки типов данных, например для файлов или чисел
    public function setValue($value)
    {
        if ($this->type == 'checkbox' && is_bool($value)) {
            $this->value = $value ? '1' : '0';
        } else {
            $this->value = $value;
        }
    }

    public function getValue()
    {
        if ($this->type == 'checkbox') {
            return $this->value === '1';
        }
        if ($this->type == 'number' && empty($this->value)) {
            return 0;
        }
        return $this->value;
    }

    // Для загрузки файлов
    public function uploadFile($file)
    {
        if ($this->type == 'file' && $file instanceof UploadedFile) {
            $fileName = Yii::getAlias('@webroot/uploads/') . $file->baseName . '.' . $file->extension;
            if ($file->saveAs($fileName)) {
                $this->value = '/uploads/' . $file->baseName . '.' . $file->extension;
            }
        }
    }
}