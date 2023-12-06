<?php

namespace common\models\user;

use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "user_profile".
 *
 * @property int     $id
 * @property int     $user_id
 * @property string  $name
 * @property string  $surname
 * @property string  $full_name
 * @property string  $avatar
 * @property int     $gender
 * @property string  $birthday
 *
 * @property User    $user
 */
class UserProfile extends \common\components\base\ActiveRecord
{

    /**
     * @return array
     */
    public static function getGenderList()
    {
        return [
            1 => Yii::t('common', 'Муж'),
            2 => Yii::t('common', 'Жен'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_profile';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [
                [
                    'user_id',
                    'gender',
                ],
                'integer',
            ],
            [['name', 'surname', 'full_name', 'avatar'], 'string', 'max' => 255],
            [['birthday'], 'safe'],
            [['user_id'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Имя',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @param User   $user
     *
     * @return bool
     */
    public static function createModel(User $user)
    {
        $model = self::findOne(['user_id' => $user->id]);
        if (!empty($model)) {
            return true;
        }

        $model = new self();
        $model->user_id              = $user->id;

        return $model->save(false);
    }
}
