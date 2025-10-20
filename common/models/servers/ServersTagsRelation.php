<?php

namespace common\models\servers;

use Yii;

/**
 * This is the model class for table "servers_tags_relation".
 *
 * @property int $id
 * @property int $server_id ID сервера
 * @property int $tag_id ID тега
 * @property string $created_at
 *
 * @property Servers $server
 * @property ServersTags $tag
 */
class ServersTagsRelation extends \common\components\base\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers_tags_relation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['server_id', 'tag_id'], 'required'],
            [['server_id', 'tag_id'], 'integer'],
            [['created_at'], 'safe'],
            [['server_id', 'tag_id'], 'unique', 'targetAttribute' => ['server_id', 'tag_id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
            [['tag_id'], 'exist', 'skipOnError' => true, 'targetClass' => ServersTags::class, 'targetAttribute' => ['tag_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'server_id' => Yii::t('common', 'Сервер'),
            'tag_id' => Yii::t('common', 'Тег'),
            'created_at' => Yii::t('common', 'Создан'),
        ];
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    /**
     * Gets query for [[Tag]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getTag()
    {
        return $this->hasOne(ServersTags::class, ['id' => 'tag_id']);
    }
}




