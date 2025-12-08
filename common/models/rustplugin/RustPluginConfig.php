<?php

namespace common\models\rustplugin;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int    $id
 * @property string $name
 * @property string $content
 * @property string $created_at
 * @property string $updated_at
 */
class RustPluginConfig extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'rust_plugin_configs';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'name'       => Yii::t('common', 'Название плагина'),
            'content'    => Yii::t('common', 'Содержание (JSON)'),
            'created_at' => Yii::t('common', 'Создано'),
            'updated_at' => Yii::t('common', 'Обновлено'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'content'], 'required'],
            [['content'], 'string'],
            [['name'], 'string', 'max' => 255],
            [['content'], 'validateJson'],
        ];
    }

    /**
     * Валидация JSON
     * @param string $attribute
     * @param array $params
     */
    public function validateJson($attribute, $params)
    {
        if (!empty($this->$attribute)) {
            json_decode($this->$attribute);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError($attribute, 'Содержимое должно быть валидным JSON');
            }
        }
    }

    /**
     * Получить декодированный JSON контент
     * @return array|null
     */
    public function getDecodedContent()
    {
        if (empty($this->content)) {
            return null;
        }
        
        $decoded = json_decode($this->content, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Установить контент из массива
     * @param array $data
     */
    public function setContentFromArray($data)
    {
        $this->content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

