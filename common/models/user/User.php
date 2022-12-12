<?php

namespace common\models\user;

use common\components\helpers\Role;
use common\models\auth\AuthAssignment;
use common\models\payment\Payment;
use yii\base\NotSupportedException;
use Yii;
use yii\web\IdentityInterface;
use common\components\base\ActiveRecord;

/**
 * @property int                 $id
 * @property string              $email
 * @property string              $password_hash
 * @property string              $auth_key
 * @property int                 $ref_code
 * @property string              $socket_room
 * @property string              $current_language
 * @property int                 $status
 * @property string              $jwt
 * @property int                 $auto
 * @property string              $created_at
 *
 * @property UserProfile         $userProfile
 * @property UserBalance[]       $userBalances
 * @property UserBox[]           $userBoxWaitOpen
 * @property UserBox[]           $userBoxOpened
 * @property UserDrop[]          $userDrop
 * @property Payment[]           $payments
 * @property UserPromocode[]     $userPromocodes
 * @property string              $currency
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_ACTIVE       = 1;
    const STATUS_CONFIRMATION = 2;
    const STATUS_BLOCKED      = 5;
    const STATUS_TMP_BLOCKED  = 6;

    /**
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_ACTIVE       => Yii::t('common', 'Активный'),
            self::STATUS_CONFIRMATION => Yii::t('common', 'Не подтвержден'),
            self::STATUS_BLOCKED      => Yii::t('common', 'Заблокирован'),
            self::STATUS_TMP_BLOCKED  => Yii::t('common', 'Временно заблокирован'),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'user';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'email'               => Yii::t('common', 'Email'),
            'ref_code'            => Yii::t('common', 'Партнерский код'),
            'status'              => Yii::t('common', 'Статус'),
            'current_language'    => Yii::t('common', 'Выбранный язык'),
            'created_at'          => Yii::t('common', 'Дата регистрации'),
        ];
    }

    public function rules(): array
    {
        return [
            [['email', 'password_hash', 'auth_key', 'ref_code', 'socket_room', 'status'], 'required'],
            [['status', 'auto'], 'integer'],
            [['ref_code'], 'number'],
            [['ref_code'], 'string', 'min' => 9, 'max' => 10],
            [['email', 'password_hash'], 'string', 'max' => 255],
            [['auth_key', 'socket_room'], 'string', 'max' => 32],
            [['current_language', 'created_at'], 'safe'],
            [['email'], 'unique'],
        ];
    }


    /**
     * Finds an identity by the given ID.
     *
     * @param string|int $id the ID to be looked for
     * @return IdentityInterface|null the identity object that matches the given ID.
     */
    public static function findIdentity($id): ?IdentityInterface
    {
        return static::findOne($id);
    }

    /**
     * Finds an identity by the given token.
     *
     * @param string $token the token to be looked for
     * @return IdentityInterface|null the identity object that matches the given token.
     */
    public static function findIdentityByAccessToken($token, $type = null): ?IdentityInterface
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * @return int|string current user ID
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string current user auth key
     */
    public function getAuthKey(): string
    {
        return $this->auth_key;
    }

    /**
     * @param string $authKey
     * @return bool if auth key is valid for current user
     */
    public function validateAuthKey($authKey): bool
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * @param string $email
     * @param bool   $onlyActive
     *
     * @return static|null
     */
    public static function findByEmail($email, $onlyActive = true)
    {
        $attributes = ['email' => $email];

        if ($onlyActive) {
            $attributes['status'] = self::STATUS_ACTIVE;
        }

        return static::findOne($attributes);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserProfile()
    {
        return $this->hasOne(UserProfile::class, ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserBalances()
    {
        return $this->hasMany(UserBalance::class, ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserBoxWaitOpen()
    {
        return $this->hasMany(UserBox::class, ['user_id' => 'id'])
            ->andWhere(['status' => UserBox::STATUS_WAIT_OPEN]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserBoxOpened()
    {
        return $this->hasMany(UserBox::class, ['user_id' => 'id'])
                    ->andWhere(['status' => UserBox::STATUS_OPENED]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserDrop()
    {
        return $this->hasMany(UserDrop::class, ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPayments()
    {
        return $this->hasMany(Payment::class, ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserPromocodes()
    {
        return $this->hasMany(UserPromocode::class, ['user_id' => 'id']);
    }

    /**
     * @return int[]|string[]
     */
    public function getUserRoles(): array
    {
        return array_keys(Yii::$app->authManager->getRolesByUser($this->id));
    }

    /**
     * @return UserBalance
     */
    public function getPersonalBalance()
    {
        return $this->_getUserBalanceByType(UserBalance::TYPE_PERSONAL);
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return 'COIN';
    }

    /**
     * @param int $type
     *
     * @return UserBalance|null
     */
    private function _getUserBalanceByType($type)
    {
        return UserBalance::getModel($this->id, $type);
    }

    /**
     * @return bool
     */
    public function isAccessBackend()
    {
        return AuthAssignment::find()
                             ->andWhere(['user_id' => $this->id])
                             ->andWhere([
                                 'IN',
                                 'item_name',
                                 [
                                     Role::ROLE_ADMIN,
                                 ],
                             ])
                             ->exists();
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     *
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    public function generateRefCode()
    {
        do {
            $refCode = (string)(time() * rand(1, 10000000));
            $refCode = substr($refCode, 0, 10);
        } while (self::findByRefCode($refCode));

        $this->ref_code = $refCode;
    }

    /**
     * @param string $code
     *
     * @return User|null
     */
    public static function findByRefCode($code)
    {
        if (empty($code)) {
            return null;
        }

        return static::findOne(['ref_code' => $code]);
    }

    public function generateSocketRoom()
    {
        do {
            $socketRoom = md5(microtime() . '_' . Yii::$app->security->generateRandomString(10));
        } while (self::findBySocketRoom($socketRoom));

        $this->socket_room = $socketRoom;
    }

    /**
     * @param string $room
     *
     * @return User|null
     */
    public static function findBySocketRoom($room)
    {
        if (empty($room)) {
            return null;
        }

        return static::findOne(['socket_room' => $room]);
    }

    /**
     * @param string $authKey
     *
     * @return static
     */
    public function findByAuthKey($authKey)
    {
        return self::findOne(['auth_key' => $authKey]);
    }

    public function getPartnerLink() {
        return Yii::$app->params['baseUrl'] . '/p/1304069465';
    }

    public function updateCurrentLanguage()
    {
        $language = Yii::$app->language;
        if ($this->current_language != $language) {
            $this->current_language = $language;
            $this->save(false);
        }
    }

    public function getJwtToken() {
        if (empty($this->jwt)) {
            $this->jwt = Yii::$app->security->generateRandomString();
            $this->save();
        }
        return $this->jwt;
    }

    /**
     * @param string $jwt
     *
     * @return User|null
     */
    public static function findByJwtToken($jwt)
    {
        if (empty($jwt)) {
            return null;
        }

        return static::findOne(['jwt' => $jwt]);
    }
}
