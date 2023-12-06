<?php

namespace common\models\blog;

use Yii;

/**
 * This is the model class for table "blog_rating".
 *
 * @property int $id
 * @property int $blog_id
 * @property int $user_id
 * @property int $weight
 * @property string $created_at
 *
 * @property Blog $blog
 */
class BlogRating extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'blog_rating';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'blog_id', 'weight'], 'required'],
            [['user_id', 'blog_id', 'weight'], 'integer'],
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
     * Gets query for [[Blog]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBlog()
    {
        return $this->hasOne(Blog::class, ['id' => 'blog_id']);
    }
}
