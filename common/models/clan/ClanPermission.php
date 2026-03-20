<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use Yii;

/**
 * This is the model class for table "clan_permissions".
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property int $created_at
 *
 * @property ClanMemberPermission[] $memberPermissions
 */
class ClanPermission extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_permissions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['key', 'name'], 'required'],
            [['description'], 'string'],
            [['key'], 'string', 'max' => 50],
            [['name'], 'string', 'max' => 255],
            [['key'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'key' => Yii::t('common', 'Ключ'),
            'name' => Yii::t('common', 'Название'),
            'description' => Yii::t('common', 'Описание'),
            'created_at' => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * Gets query for [[MemberPermissions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMemberPermissions()
    {
        return $this->hasMany(ClanMemberPermission::class, ['permission_id' => 'id']);
    }

    /**
     * Получение списка всех предустановленных разрешений
     *
     * @return static[]
     */
    public static function getDefaultPermissions()
    {
        return static::find()->all();
    }

    /**
     * Поиск разрешения по ключу
     *
     * @param string $key
     * @return static|null
     */
    public static function findByKey($key)
    {
        return static::findOne(['key' => $key]);
    }
}

