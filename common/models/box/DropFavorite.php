<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "drop_favorite".
 *
 * @property int $id
 * @property int $user_id
 * @property int $drop_id
 * @property string $created_at
 *
 * @property User $user
 * @property Drop $drop
 */
class DropFavorite extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'drop_favorite';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'drop_id'], 'required'],
            [['user_id', 'drop_id'], 'integer'],
            [['created_at'], 'safe'],
            [['user_id', 'drop_id'], 'unique', 'targetAttribute' => ['user_id', 'drop_id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['drop_id'], 'exist', 'skipOnError' => true, 'targetClass' => Drop::class, 'targetAttribute' => ['drop_id' => 'id']],
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
            'drop_id' => Yii::t('common', 'Товар'),
            'created_at' => Yii::t('common', 'Дата добавления'),
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
     * Gets query for [[Drop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDrop()
    {
        return $this->hasOne(Drop::class, ['id' => 'drop_id']);
    }

    /**
     * Проверяет, находится ли товар в избранном у пользователя
     *
     * @param int $userId
     * @param int $dropId
     * @return bool
     */
    public static function isFavorite($userId, $dropId)
    {
        return self::find()
            ->andWhere(['user_id' => $userId, 'drop_id' => $dropId])
            ->exists();
    }

    /**
     * Добавляет товар в избранное
     *
     * @param int $userId
     * @param int $dropId
     * @return bool
     */
    public static function addToFavorite($userId, $dropId)
    {
        $exists = self::find()
            ->andWhere(['user_id' => $userId, 'drop_id' => $dropId])
            ->exists();

        if ($exists) {
            return true; // Уже в избранном
        }

        $favorite = new self();
        $favorite->user_id = $userId;
        $favorite->drop_id = $dropId;
        $favorite->created_at = date('Y-m-d H:i:s');

        return $favorite->save(false);
    }

    /**
     * Удаляет товар из избранного
     *
     * @param int $userId
     * @param int $dropId
     * @return bool
     */
    public static function removeFromFavorite($userId, $dropId)
    {
        $favorite = self::find()
            ->andWhere(['user_id' => $userId, 'drop_id' => $dropId])
            ->one();

        if ($favorite) {
            return $favorite->delete();
        }

        return true; // Уже удален
    }

    /**
     * Получает список ID товаров в избранном у пользователя
     *
     * @param int $userId
     * @return array
     */
    public static function getFavoriteDropIds($userId)
    {
        return self::find()
            ->select('drop_id')
            ->andWhere(['user_id' => $userId])
            ->column();
    }
}


