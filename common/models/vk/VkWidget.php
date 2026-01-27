<?php

namespace common\models\vk;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "vk_widgets".
 *
 * @property int $id
 * @property int $group_id ID сообщества ВК
 * @property int $app_id ID приложения ВК
 * @property string|null $logo_icon_id ID иконки логотипа
 * @property string|null $api_url URL API для получения данных о серверах
 * @property string|null $access_token Токен доступа для обновления виджета (зашифрован)
 * @property int $status Статус: 0 - отключен, 1 - активен
 * @property string $created_at Дата создания
 * @property string $updated_at Дата обновления
 */
class VkWidget extends ActiveRecord
{
    const STATUS_DISABLED = 0;
    const STATUS_ACTIVE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vk_widgets';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['group_id', 'app_id'], 'required'],
            [['group_id', 'app_id', 'status'], 'integer'],
            [['access_token'], 'string'],
            [['logo_icon_id', 'api_url'], 'string', 'max' => 255],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'group_id' => 'ID сообщества ВК',
            'app_id' => 'ID приложения ВК',
            'logo_icon_id' => 'ID иконки логотипа',
            'api_url' => 'URL API',
            'access_token' => 'Токен доступа',
            'status' => 'Статус',
            'created_at' => 'Дата создания',
            'updated_at' => 'Дата обновления',
        ];
    }

    /**
     * Получить или создать виджет по group_id
     * 
     * @param int $groupId
     * @param int $appId
     * @return static
     */
    public static function findOrCreate($groupId, $appId)
    {
        $widget = static::findOne(['group_id' => $groupId, 'app_id' => $appId]);
        
        if (!$widget) {
            $widget = new static();
            $widget->group_id = $groupId;
            $widget->app_id = $appId;
            $widget->status = self::STATUS_ACTIVE;
            $widget->save(false);
        }
        
        return $widget;
    }

    /**
     * Получить все активные виджеты
     * 
     * @return static[]
     */
    public static function getActiveWidgets()
    {
        return static::find()
            ->where(['status' => self::STATUS_ACTIVE])
            ->all();
    }

    /**
     * Получить ключ для шифрования (используем сервисный ключ или фиксированный ключ)
     * 
     * @return string
     */
    protected function getEncryptionKey()
    {
        // Используем сервисный ключ, если он есть, иначе фиксированный ключ из настроек приложения
        $key = Yii::$app->settings->get('vk_app_sever_key');
        if (!$key) {
            // Используем фиксированный ключ на основе ID приложения (если доступен)
            $key = md5(Yii::$app->id . '_vk_widget_token_key');
        }
        return $key;
    }

    /**
     * Зашифровать токен
     * 
     * @param string $token
     * @return string|null
     */
    public function encryptToken($token)
    {
        if (empty($token)) {
            return null;
        }
        
        try {
            $key = $this->getEncryptionKey();
            return base64_encode(Yii::$app->security->encryptByKey($token, $key));
        } catch (\Exception $e) {
            Yii::error('Failed to encrypt token: ' . $e->getMessage(), 'vk-widget');
            return null;
        }
    }

    /**
     * Расшифровать токен
     * 
     * @return string|null
     */
    public function decryptToken()
    {
        if (empty($this->access_token)) {
            return null;
        }
        
        try {
            $key = $this->getEncryptionKey();
            return Yii::$app->security->decryptByKey(base64_decode($this->access_token), $key);
        } catch (\Exception $e) {
            Yii::error('Failed to decrypt token: ' . $e->getMessage(), 'vk-widget');
            return null;
        }
    }
}

