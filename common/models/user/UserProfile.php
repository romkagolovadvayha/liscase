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
 * @property string  $phone
 * @property int     $phone_is_confirm
 * @property string  $avatar
 * @property int     $gender
 * @property string  $birthday
 * @property string  $trade_link
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
                    'phone_is_confirm',
                    'gender',
                ],
                'integer',
            ],
            [['name', 'surname', 'trade_link', 'full_name', 'avatar'], 'string', 'max' => 255],
            [['phone'], 'string', 'max' => 20],
            [['birthday'], 'safe'],
            [['user_id'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Имя',
            'trade_link' => 'Ссылка на страницу обмена Steam',
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
     * @param string $name
     * @param string $surname
     * @param string $phone
     *
     * @return bool
     */
    public static function createModel(User $user, $name)
    {
        $model = self::findOne(['user_id' => $user->id]);
        if (!empty($model)) {
            return true;
        }

        $model = new self();
        $model->user_id              = $user->id;
        $model->name                 = $name;
        $model->full_name            = trim($name);

        return $model->save();
    }

    /**
     * @param string $phone
     *
     * @return static|null
     */
    public static function findByPhone($phone)
    {
        $attributes = ['phone' => $phone];

        return static::findOne($attributes);
    }
}
