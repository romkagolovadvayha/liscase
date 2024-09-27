<?php

namespace common\models\user;

use common\components\helpers\Role;
use common\components\oauth\Steam;
use common\components\web\Cookie;
use common\models\auth\AuthAssignment;
use common\models\invoice\Invoice;
use common\models\invoice\Deposit;
use common\models\profit\Profit;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\stats\Wipe;
use yii\base\BaseObject;
use yii\base\NotSupportedException;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;
use common\components\base\ActiveRecord;

/**
 * @property int             $id
 * @property string          $email
 * @property string          $steam_id
 * @property int             $telegram_chat_id
 * @property string          $username
 * @property string          $password_hash
 * @property string          $auth_key
 * @property int             $ref_code
 * @property string          $socket_room
 * @property string          $current_language
 * @property int             $status
 * @property string          $jwt
 * @property bool            $parent_skin_send
 * @property int             $auto
 * @property string          $server_tag
 * @property string          $created_at
 * @property string          $updated_at
 * @property string          $banned_at
 * @property string          $unbanned_at
 * @property int             $ban_reason
 * @property int             $ban_by
 * @property string          $discord
 * @property bool            $rustru_activated
 * @property int             $rustru_scrap_confirm
 * @property int             $rustru_scrap_wait
 *
 * @property UserProfile     $userProfile
 * @property UserBalance[]   $userBalances
 * @property Invoice[]       $invoices
 * @property UserBox[]       $userBoxWaitOpen
 * @property UserBox[]       $userBoxOpened
 * @property UserDrop[]      $userDrop
 * @property Deposit[]       $deposits
 * @property UserPromocode[] $userPromocodes
 * @property UserTask        $userTasks
 * @property string          $currency
 * @property Auth            $auth
 * @property UserTree        $userTree
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_ACTIVE       = 1;
    const STATUS_CONFIRMATION = 2;
    const STATUS_BLOCKED      = 5;
    const STATUS_TMP_BLOCKED  = 6;

    const REASON_GAME_3 = 1;
    const REASON_CHEATING = 2;
    const REASON_TEAM_CHEATING = 3;
    const REASON_GAME_1 = 4;
    const REASON_NOT_REASON = 5;
    const REASON_MACROS = 6;
    const REASON_MULTIACC = 7;
    const REASON_BAN_OTHER_PROJECT = 8;
    const REASON_BAN_CANCEL_CHECK = 9;

    const REASON_MUTE_SPAM = 1;
    const REASON_MUTE_FLUD = 2;
    const REASON_MUTE_OSCORB = 3;
    const REASON_MUTE_OTHER = 4;

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
     * @return array
     */
    public static function getReasonList(): array
    {
        return [
            self::REASON_GAME_3       => Yii::t('common', 'Игра 3+'),
            self::REASON_GAME_1       => Yii::t('common', 'Игра 1+'),
            self::REASON_CHEATING       => Yii::t('common', 'Читер'),
            self::REASON_TEAM_CHEATING       => Yii::t('common', 'Игра с читером'),
            self::REASON_MACROS      => Yii::t('common', 'Макросы'),
            self::REASON_MULTIACC      => Yii::t('common', 'Мульти Аккаунт'),
            self::REASON_BAN_OTHER_PROJECT       => Yii::t('common', 'Бан на другом проекте'),
            self::REASON_BAN_CANCEL_CHECK       => Yii::t('common', 'Отказ от проверки'),
            self::REASON_NOT_REASON       => Yii::t('common', 'Причина не указана'),
        ];
    }

    /**
     * @return array
     */
    public static function getReasonMuteList(): array
    {
        return [
            self::REASON_MUTE_SPAM     => Yii::t('common', 'Спам'),
            self::REASON_MUTE_FLUD       => Yii::t('common', 'Флуд'),
            self::REASON_MUTE_OSCORB       => Yii::t('common', 'Оскорбление родных'),
            self::REASON_MUTE_OTHER       => Yii::t('common', 'Без причины'),
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
            'username'               => Yii::t('common', 'Ник'),
            'steam_id'               => Yii::t('common', 'Steam ID'),
            'ref_code'            => Yii::t('common', 'Партнерский код'),
            'status'              => Yii::t('common', 'Статус'),
            'current_language'    => Yii::t('common', 'Выбранный язык'),
            'created_at'          => Yii::t('common', 'Дата регистрации'),
            'banned_at'          => Yii::t('common', 'Дата Бана'),
            'unbanned_at'          => Yii::t('common', 'Разбан'),
            'ban_reason'          => Yii::t('common', 'Причина'),
            'ban_by'          => Yii::t('common', 'Кем забанен'),
        ];
    }

    public function rules(): array
    {
        return [
            [['email', 'password_hash', 'auth_key', 'ref_code', 'socket_room', 'status'], 'required'],
            [['status', 'auto', 'steam_id'], 'integer'],
            [['ref_code'], 'number'],
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
     * @param string $chatId
     *
     * @return static|null
     */
    public static function findByChatId($chatId)
    {
        $attributes = ['telegram_chat_id' => $chatId];

        return static::findOne($attributes);
    }

    public static function findBySteamId($steamId, $updated = false)
    {
        if (strlen($steamId) !== 17) {
            return null;
        }
        /** @var User $user */
        $user = static::find()
                      ->andWhere(['steam_id' => $steamId])
                      ->one();

            if (empty($user)) {
                $dbTransaction = Yii::$app->db->beginTransaction();
                try {
                    $infoUser       = Steam::getInfoUser($steamId);
                    if (empty($infoUser)) {
                        $dbTransaction->rollBack();
                        return null;
                    }
                    $user           = new User();
                    $user->email    = "{$steamId}@steam.com";
                    $user->steam_id = $steamId;
                    $user->auto = 1;
                    $user->username = $infoUser[0]['personaname'];
                    $user->setPassword(Yii::$app->security->generateRandomString());
                    $user->status = User::STATUS_ACTIVE;
                    $user->generateAuthKey();
                    $user->generateRefCode();
                    $user->generateSocketRoom();
                    if ($user->save()) {
                        $auth = new Auth(
                            [
                                'user_id'   => $user->id,
                                'source'    => 'steam',
                                'source_id' => (string)$steamId,
                            ]
                        );
                        $auth->save();
                        $dbTransaction->commit();
                        UserTree::appendUser($user->id, 509);
                        UserProfile::createModel($user, $infoUser[0]['personaname']);
                        try {
                            $avatar                    = self::_loadImage($infoUser[0]['avatarfull'], $steamId);
                            $user->userProfile->avatar = $avatar;
                        } catch (\Exception $ex) {
                        }
                        $user->userProfile->save();
                    }
                } catch (\Exception $e) {
                    $dbTransaction->rollBack();
                    Yii::error("User Registration: " . $e->getMessage(), 'error');
                    throw new \Exception(Yii::t('common', 'Произошла ошибка, попробуйте обновить страницу!'));
                }
            } elseif ($updated && (empty($user->updated_at) || strtotime($user->updated_at) + 60*60*24*7 < time())) {
                $infoUser       = Steam::getInfoUser($steamId);
                $user->updated_at = date('Y-m-d H:i:s');
                $user->username = $infoUser[0]['personaname'];
                $user->save();
                $avatar = self::_loadImage($infoUser[0]['avatarfull'], $steamId);
                $user->userProfile->name = $infoUser[0]['personaname'];
                $user->userProfile->avatar = $avatar;
                $user->userProfile->save();
            }

        return $user;
    }

    public static function _loadImage($imageUrl, $id) {
        $uploadDir = \Yii::getAlias('@frontend/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
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
    public function getAuth()
    {
        return $this->hasOne(Auth::class, ['user_id' => 'id']);
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
    public function getUserTree()
    {
        return $this->hasOne(UserTree::class, ['user_id' => 'id']);
    }

    /**
     * @return User|null
     */
    public function getParentUser()
    {
        if (empty($this->userTree) || $this->userTree->level == 0) {
            return null;
        }

        return $this->userTree->parentUser;
    }

    /**
     * @param int|null $depth
     *
     * @return \yii\db\ActiveQuery
     */
    public function getChildrenUserTreeQuery($depth = null)
    {
        $userTree = UserTree::find()
            ->andWhere(['parent_user_id' => $this->id]);

        return $userTree;
    }

    /**
     * @return bool
     */
    public function hasChildrenUsers()
    {
        return UserTree::find()->andWhere(['parent_user_id' => $this->id])->exists();
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
    public function getUserTasks()
    {
        return $this->hasMany(UserTask::class, ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getInvoices()
    {
        return $this->hasMany(Invoice::class, ['user_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeposits()
    {
        return $this->hasMany(Deposit::class, ['user_id' => 'id']);
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
                                     Role::ROLE_MODERATOR,
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
        return Yii::$app->params['baseUrl'] . '/p/' . Yii::$app->user->identity->ref_code;
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

    public function getAvatar() {
        return $this->userProfile->avatar;
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

    /**
     * @param User $user
     */
    public static function parentBonus($user)
    {
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->andWhere('db_host IS NOT NULL')
                          ->all();

        $onlineTime = 0;
        foreach ($servers as $server) {
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            $player = Statistics::find()
                                ->cache(180)
                                ->andWhere(['steam_id' => $user->steam_id])
                                ->andWhere(['server_tag' => $server->tag])
                                ->andWhere(['wipe' => $wipeDate])
                                ->indexBy('key')
                                ->all();
            if (empty($player)) {
                continue;
            }
            $onlineTime += Statistics::getParam($player, 'playtime');
        }

        if ($onlineTime < 60) {
            return;
        }

        $user->userProfile->parent_bonus = 1;
        $user->userProfile->save(false);

        $profit = new Profit();
        $profit->status = 1;
        $profit->type = Profit::TYPE_REFERRAL;
        $profit->amount = 30;
        $profit->user_balance_id = $user->getParentUser()->getPersonalBalance()->id;
        $profit->comment = Yii::t('common', 'Бонус за приглашенного пользователя "{PARAMS_USER_NAME}"', [
            'PARAMS_USER_NAME' => $user->username
        ],'ru-RU');
        $profit->created_at = date('Y-m-d H:i:s');
        $profit->save(false);
    }

    /**
     * @param int $systemId
     * @param int $telegramChatId
     *
     * @return null|static
     */
    public static function getUserByTelegramChat($systemId, $telegramChatId)
    {
        return self::findOne([
                                 'system_id'        => $systemId,
                                 'telegram_chat_id' => $telegramChatId,
                             ]);
    }

    public function mute($reason) {
        $reasonText = ArrayHelper::getValue(User::getReasonMuteList(), $reason);
        $command = "bcm.mute \"{$this->steam_id}\" 5h \"{$reasonText}\"";
        RconTasks::execute($command);
        return true;
    }

    public function ban($reason, $banBy = null, $bannedAt = null, $rustcheck = true, $siteban = true, $task = true) {
        $serversBan = [];
        if ($siteban) {
            $this->ban_by     = $banBy;
            $this->ban_reason = $reason;
            $this->status     = User::STATUS_BLOCKED;
            $this->unbanned_at = null;
            if (empty($bannedAt)) {
                $bannedAt = date('Y-m-d H:i:s');
            }
            $this->banned_at = $bannedAt;
            /** @var Servers[] $servers */
            $servers = Servers::find()->cache(30)->andWhere(['status' => Servers::STATUS_ACTIVE])->all();
            if (in_array($reason, [self::REASON_GAME_3])) {
                $serversBan = ['max3'];
                foreach ($servers as $server) {
                    if (in_array($server->tag, $serversBan)) {
                        $unbannedAt = $server->next_wipe;
                        break;
                    }
                }
            }
            if (in_array($reason, [self::REASON_GAME_1])) {
                $serversBan = ['solo'];
                foreach ($servers as $server) {
                    if (in_array($server->tag, $serversBan)) {
                        $unbannedAt = $server->next_wipe;
                        break;
                    }
                }
            }
            if (!empty($unbannedAt)) {
                $this->status      = User::STATUS_ACTIVE;
                $this->unbanned_at = $unbannedAt;
            }
            $this->save();
        }
        $reasonText = ArrayHelper::getValue(User::getReasonList(), $reason);
        if ($task) {
            $command = "helper ban \"{$this->steam_id}\" \"{$reasonText}\"";
            RconTasks::execute($command, $serversBan);
        }
        if ($rustcheck && $reason !== User::REASON_NOT_REASON) {
            Yii::$app->rustCheck->ban($this->steam_id, $reasonText);
        }

        /** @var UserChecking $userChecking */
        $userChecking = UserChecking::find()
                             ->andWhere(['user_id' => $this->id])
                             ->andWhere(['status' => UserChecking::STATUS_CHECKING])
                             ->one();
        if (!empty($userChecking)) {
            $userChecking->status  = UserChecking::STATUS_DONE;
            $userChecking->done_at = date('Y-m-d H:i:s');
            $userChecking->save();
        }

        return true;
    }

    public function unban() {
        $this->ban_by = null;
        $this->ban_reason = null;
        $this->banned_at = null;
        $this->unbanned_at = null;
        $this->status = User::STATUS_ACTIVE;
        $this->save();

        $command = "unban \"{$this->steam_id}\"";
        RconTasks::execute($command);

        Yii::$app->rustCheck->unban($this->steam_id);
        return true;
    }

    /**
     * @param array $roles
     *
     * @return bool
     */
    public function canRoles($roles = [])
    {
        foreach ($roles as $role) {
            if (Yii::$app->authManager->checkAccess($this->id, $role)) {
                return true;
            }
        }

        return false;
    }
}
