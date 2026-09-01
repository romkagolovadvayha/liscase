<?php
namespace common\models\site;

use Yii;
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
 * @property bool   $is_translate
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
            [['name', 'category', 'type', 'code'], 'required'],
            [['name', 'category', 'code'], 'trim'],
            [['name', 'category'], 'string', 'max' => 255],
            [['type'], 'in', 'range' => ['text', 'color', 'image', 'video', 'file', 'number', 'checkbox', 'radio', 'longtext', 'password']],
            [['value'], 'string'],
            [['is_translate'], 'integer'],
            [['code'], 'string', 'max' => 255],
            [['category', 'code'], 'match', 'pattern' => '/^[A-Za-z0-9_-]+$/u', 'message' => 'Используйте латинские буквы, цифры, дефис и подчёркивание.'],
            [['code'], 'unique', 'targetAttribute' => ['category', 'code'], 'message' => 'Такой системный код уже есть в этой категории.'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => Yii::t('common', 'Название'),
            'category' => Yii::t('common', 'Категория'),
            'type' => Yii::t('common', 'Тип'),
            'value' => Yii::t('common', 'Значение по умолчанию'),
            'code' => Yii::t('common', 'Короткий системный код'),
            'is_translate' => Yii::t('common', 'Переводить на другие языки?'),
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
        if ($this->type === 'image' || $this->type === 'video') {
            // Если это изображение или видео, возвращаем публичный URL из S3
            if (!empty($this->value) && strpos($this->value, '/uploads/') === 0) {
                // Получаем s3_publicUrl напрямую из базы, чтобы избежать рекурсии
                // Используем статический кэш для оптимизации
                static $s3PublicUrlCache = null;
                if ($s3PublicUrlCache === null) {
                    $s3PublicUrlCache = self::find()
                        ->where(['category' => 's3', 'code' => 'publicUrl'])
                        ->select('value')
                        ->scalar();
                    
                    if (empty($s3PublicUrlCache)) {
                        // Fallback на значение из params, если настройка не найдена
                        $s3PublicUrlCache = Yii::$app->params['s3Url'] ?? '';
                    }
                }
                
                return rtrim($s3PublicUrlCache, '/') . $this->value;
            }
        }
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
