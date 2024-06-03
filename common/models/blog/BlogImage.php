<?php

namespace common\models\blog;

use Yii;

/**
 * This is the model class for table "blog_image".
 *
 * @property int $id
 * @property string $link
 * @property string $description
 * @property int $blog_id
 * @property string $created_at
 *
 * @property Blog $blog
 */
class BlogImage extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'blog_image';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['link', 'blog_id'], 'required'],
            [['link', 'description'], 'string'],
            [['blog_id'], 'integer'],
            [['created_at'], 'safe'],
            [['blog_id'], 'exist', 'skipOnError' => true, 'targetClass' => Blog::class, 'targetAttribute' => ['blog_id' => 'id']],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = date('Y-m-d H:i:s');
        }

        return parent::beforeSave($insert);
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'link' => 'Link',
            'blog_id' => 'Blog ID',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Blog]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBlog()
    {
        return $this->hasOne(Blog::class, ['id' => 'blog_id']);
    }
}
