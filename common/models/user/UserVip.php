<?php

namespace common\models\user;

use common\components\base\ActiveRecord;
use Yii;

/**
 * This is the model class for table "user_vip".
 *
 * @property int       $id
 * @property int       $user_id
 * @property string    $expires_at
 * @property string    $created_at
 * @property string    $updated_at
 *
 * @property User      $user
 */
class UserVip extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_vip';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'expires_at'], 'required'],
            [['user_id'], 'integer'],
            [['expires_at', 'created_at', 'updated_at'], 'safe'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'user_id' => Yii::t('common', 'Пользователь'),
            'expires_at' => Yii::t('common', 'Дата окончания'),
            'created_at' => Yii::t('common', 'Создано'),
            'updated_at' => Yii::t('common', 'Обновлено'),
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Проверяет, активен ли VIP (не истек ли срок)
     *
     * @return bool
     */
    public function isActive()
    {
        return strtotime($this->expires_at) > time();
    }

    /**
     * Получает VIP запись для пользователя (одна запись на пользователя)
     *
     * @param int $userId
     * @return UserVip|null
     */
    public static function getVip($userId)
    {
        return static::find()
            ->where(['user_id' => $userId])
            ->one();
    }

    /**
     * Получает активный VIP для пользователя
     *
     * @param int $userId
     * @return UserVip|null
     */
    public static function getActiveVip($userId)
    {
        $vip = static::getVip($userId);
        if ($vip && $vip->isActive()) {
            return $vip;
        }
        return null;
    }

    /**
     * Создает или продлевает VIP для пользователя
     * Для каждого пользователя всегда используется только одна запись
     *
     * @param int $userId
     * @param int|string $expiresAt Timestamp или дата в формате Y-m-d H:i:s
     * @return UserVip
     */
    public static function createOrExtend($userId, $expiresAt)
    {
        // Ищем существующую запись для пользователя (независимо от того, активна она или нет)
        $vip = static::getVip($userId);
        
        // Конвертируем expiresAt в формат DATETIME, если нужно
        if (is_numeric($expiresAt)) {
            $newExpiresTimestamp = $expiresAt;
            $expiresAt = date('Y-m-d H:i:s', $expiresAt);
        } else {
            $newExpiresTimestamp = strtotime($expiresAt);
        }
        
        if ($vip) {
            // Если запись существует, обновляем её
            $currentExpires = strtotime($vip->expires_at);
            $now = time();
            
            // Если VIP еще не истек, добавляем 30 дней к текущей дате окончания
            if ($currentExpires > $now) {
                // Добавляем 30 дней к текущей дате окончания
                $vip->expires_at = date('Y-m-d H:i:s', strtotime('+30 days', $currentExpires));
            } else {
                // Если VIP истек, устанавливаем новую дату (текущее время + 30 дней)
                $vip->expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            }
            $vip->updated_at = date('Y-m-d H:i:s');
            $vip->save(false);
            return $vip;
        } else {
            // Создаем новую запись
            $vip = new static();
            $vip->user_id = $userId;
            $vip->expires_at = date('Y-m-d H:i:s', strtotime('+30 days')); // Всегда 30 дней для новой записи
            $vip->created_at = date('Y-m-d H:i:s');
            $vip->updated_at = date('Y-m-d H:i:s');
            $vip->save(false);
            return $vip;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert && empty($this->created_at)) {
                $this->created_at = date('Y-m-d H:i:s');
            }
            $this->updated_at = date('Y-m-d H:i:s');
            return true;
        }
        return false;
    }
}

