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
    const ROLE_AUTH_CUPBOARDS = 'ROLE_AUTH_CUPBOARDS';
    const ROLE_AUTH_AA = 'ROLE_AUTH_AA';
    const ROLE_AUTH_DOORS = 'ROLE_AUTH_DOORS';
    const ROLE_AUTH_TURRETS = 'ROLE_AUTH_TURRETS';

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
            self::ROLE_AUTH_CUPBOARDS => 7,
            self::ROLE_AUTH_AA => 8,
            self::ROLE_AUTH_DOORS => 9,
            self::ROLE_AUTH_TURRETS => 10,
        ];
    }

    /**
     * Получить названия ролей на русском языке
     * @return array
     */
    public static function getRoleNames()
    {
        return [
            self::ROLE_MEMBER => 'Участник клана',
            self::ROLE_EDIT_INFO => 'Изменение информации о клане',
            self::ROLE_QUESTION => 'Управление заявками на вступление',
            self::ROLE_EDIT_MEMBERS => 'Управление участниками клана',
            self::ROLE_EDIT_PAGES => 'Управление страницами клана',
            self::ROLE_INVITE => 'Создание приглашений в клан',
            self::ROLE_AUTH_CUPBOARDS => 'Авторизация в шкафах',
            self::ROLE_AUTH_AA => 'Авторизация в ПВО',
            self::ROLE_AUTH_DOORS => 'Авторизация в дверях',
            self::ROLE_AUTH_TURRETS => 'Авторизация в турелях',
        ];
    }

    /**
     * Получить название роли на русском языке
     * @param string $roleKey
     * @return string
     */
    public static function getRoleName($roleKey)
    {
        $names = self::getRoleNames();
        return isset($names[$roleKey]) ? $names[$roleKey] : $roleKey;
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
