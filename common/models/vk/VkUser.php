<?php

namespace common\models\vk;

use Yii;

/**
 * This is the model class for table "vk_user".
 *
 * @property int $id
 * @property int $vk_user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $screen_name
 * @property bool $can_send_message
 * @property string $created_at
 * @property string $updated_at
 */
class VkUser extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'vk_user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['vk_user_id'], 'required'],
            [['vk_user_id'], 'integer'],
            [['can_send_message'], 'boolean'],
            [['created_at', 'updated_at'], 'safe'],
            [['first_name', 'last_name', 'screen_name'], 'string', 'max' => 255],
            [['vk_user_id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'vk_user_id' => 'VK User ID',
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'screen_name' => 'Screen Name',
            'can_send_message' => 'Можно отправлять сообщения',
            'created_at' => 'Дата создания',
            'updated_at' => 'Дата обновления',
        ];
    }

    /**
     * Создание или обновление записи VK пользователя
     * @param int $vkUserId
     * @param array $userData Данные пользователя из VK API
     * @param bool $canSendMessage
     * @return VkUser
     */
    public static function createOrUpdate($vkUserId, $userData = [], $canSendMessage = false)
    {
        $model = self::findOne(['vk_user_id' => $vkUserId]);
        
        if ($model === null) {
            $model = new self();
            $model->vk_user_id = $vkUserId;
            $model->created_at = date('Y-m-d H:i:s');
        }
        
        $model->first_name = $userData['first_name'] ?? null;
        $model->last_name = $userData['last_name'] ?? null;
        $model->screen_name = $userData['screen_name'] ?? null;
        $model->can_send_message = $canSendMessage;
        $model->updated_at = date('Y-m-d H:i:s');
        
        $model->save(false);
        
        return $model;
    }

    /**
     * Получить список всех пользователей, которым можно отправлять сообщения
     * @return array Массив VK User ID
     */
    public static function getUsersWithPermission()
    {
        return self::find()
            ->select('vk_user_id')
            ->where(['can_send_message' => true])
            ->column();
    }
}

