<?php

namespace common\models\clan;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "user_role".
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $clan_id
 * @property int $role
 * @property string|null $created_at
 *
 * @property Clan $clan
 * @property User $user
 */
class UserRole extends \yii\db\ActiveRecord
{

    const ROLE_MEMBER = 'ROLE_MEMBER';
    const ROLE_EDIT_INFO = 'ROLE_EDIT_INFO';
    const ROLE_QUESTION = 'ROLE_QUESTION';
    const ROLE_EDIT_MEMBERS = 'ROLE_EDIT_MEMBERS';
    const ROLE_EDIT_PAGES = 'ROLE_EDIT_PAGES';
    const ROLE_INVITE = 'ROLE_INVITE';

    /**
     * {@inheritdoc}
     */
    public static function codes()
    {
        return [
            self::ROLE_MEMBER => 1,
            self::ROLE_EDIT_INFO => 2,
            self::ROLE_QUESTION => 3,
            self::ROLE_EDIT_MEMBERS => 4,
            self::ROLE_EDIT_PAGES => 5,
            self::ROLE_INVITE => 6,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_role';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'clan_id', 'role'], 'integer'],
            [['created_at'], 'safe'],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'clan_id' => 'Clan ID',
            'role' => 'Role',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Clan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
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
}
