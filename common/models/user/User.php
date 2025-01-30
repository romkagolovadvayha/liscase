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
use yii\helpers\HtmlPurifier;
use yii\web\IdentityInterface;
use common\components\base\ActiveRecord;
use yii\web\JsExpression;

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
 * @property string          $last_visit_server_at
 * @property string          $banned_at
 * @property string          $unbanned_at
 * @property int             $ban_reason
 * @property int             $ban_by
 * @property string          $discord
 * @property bool            $rustru_activated
 * @property int             $rustru_scrap_confirm
 * @property int             $rustru_scrap_wait
 * @property int             $is_gamer
 * @property int             $server_id
 * @property bool            $raid_notify
 * @property bool            $store
 * @property bool            $is_stats
 * @property string          $blocked_support_at
 * @property bool            $blocked_support
 * @property string          $stat_status
 * @property int             $avatar_frame
 * @property bool            $is_email
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
 * @property Servers         $server
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
    const REASON_BAN_RESULT = 10;
    const REASON_CLEAN = 11;

    const REASON_MUTE_SPAM = 1;
    const REASON_MUTE_FLUD = 2;
    const REASON_MUTE_OSCORB = 3;
    const REASON_MUTE_OTHER = 4;
    const REASON_MUTE_ROZN = 5;

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
            self::REASON_GAME_3            => Yii::t('common', 'Игра 3+'),
            self::REASON_GAME_1            => Yii::t('common', 'Игра 1+'),
            self::REASON_CHEATING          => Yii::t('common', 'Читер'),
            self::REASON_TEAM_CHEATING     => Yii::t('common', 'Игра с читером'),
            self::REASON_MACROS            => Yii::t('common', 'Макросы'),
            self::REASON_MULTIACC          => Yii::t('common', 'Мульти Аккаунт'),
            self::REASON_BAN_OTHER_PROJECT => Yii::t('common', 'Бан на другом проекте'),
            self::REASON_BAN_RESULT        => Yii::t('common', 'По результатам проверки'),
            self::REASON_CLEAN             => Yii::t('common', 'Чистка компьютера'),
            self::REASON_BAN_CANCEL_CHECK  => Yii::t('common', 'Отказ от проверки'),
            self::REASON_NOT_REASON        => Yii::t('common', 'Причина не указана'),
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
            self::REASON_MUTE_ROZN       => Yii::t('common', 'Межнациональная рознь'),
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
            'store'          => Yii::t('common', 'Доступ к магазину на без донатном сервере?'),
            'is_stats'          => Yii::t('common', 'Показывать в статистике?'),
        ];
    }

    public function rules(): array
    {
        return [
            [['email', 'password_hash', 'auth_key', 'ref_code', 'socket_room', 'status'], 'required'],
            [['status', 'auto', 'steam_id', 'store', 'is_stats', 'avatar_frame'], 'integer'],
            [['ref_code'], 'number'],
            [['email', 'password_hash', 'stat_status'], 'string', 'max' => 255],
            [['auth_key', 'socket_room'], 'string', 'max' => 32],
            [['current_language', 'created_at'], 'safe'],
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
                    $user->username = HtmlPurifier::process($infoUser[0]['personaname']);
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
                        $user->userProfile->name = HtmlPurifier::process($infoUser[0]['personaname']);
                        try {
                            $avatar                    = self::_loadImage($infoUser[0]['avatarfull'], $steamId);
                            $user->userProfile->avatar = $avatar;
                        } catch (\Exception $ex) {
                        }
                        $user->userProfile->save();
                    }
                } catch (\Exception $e) {
                    $dbTransaction->rollBack();
                    Yii::$app->telegramChats->sendMessage("User findBySteamId: {$steamId} " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    throw new \Exception(Yii::t('common', 'Произошла ошибка, попробуйте обновить страницу!'));
                }
            } elseif ($updated && (empty($user->updated_at) || strtotime($user->updated_at) + 60*60*24*7 < time())) {
                $infoUser       = Steam::getInfoUser($steamId);
                $user->updated_at = date('Y-m-d H:i:s');
                $user->username = HtmlPurifier::process($infoUser[0]['personaname']);
                $user->save();
                $avatar = self::_loadImage($infoUser[0]['avatarfull'], $steamId);
                $user->userProfile->name = HtmlPurifier::process($infoUser[0]['personaname']);
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
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
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
        if (empty($this->userProfile)) {
            return '';
        }
        return Yii::$app->params['cdnUrl'] . $this->userProfile->avatar;
    }

    public function getStatus() {
        return strtotime($this->last_visit_server_at) >= time() - 2 * 60;
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
                          ->orderBy(['sort' => SORT_ASC])
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
//            $servers = Servers::find()
//                              ->cache(30)
//                              ->andWhere(['status' => Servers::STATUS_ACTIVE])
//                              ->orderBy(['sort' => SORT_ASC])
//                              ->all();
//            if (in_array($reason, [self::REASON_GAME_3])) {
//                $serversBan = ['max3'];
//                foreach ($servers as $server) {
//                    if (in_array($server->tag, $serversBan)) {
//                        $unbannedAt = $server->next_wipe;
//                        break;
//                    }
//                }
//            }
//            if (in_array($reason, [self::REASON_GAME_1])) {
//                $serversBan = ['solo'];
//                foreach ($servers as $server) {
//                    if (in_array($server->tag, $serversBan)) {
//                        $unbannedAt = $server->next_wipe;
//                        break;
//                    }
//                }
//            }
//            if (!empty($unbannedAt)) {
//                $this->status      = User::STATUS_ACTIVE;
//                $this->unbanned_at = $unbannedAt;
//            }
            $this->save(false);
        }
//        $reasonText = ArrayHelper::getValue(User::getReasonList(), $reason);
//        if ($task) {
//            $command = "helper ban \"{$this->steam_id}\" \"{$reasonText}\"";
//            RconTasks::execute($command, $serversBan);
//        }
//        if (YII_ENV_PROD && $rustcheck && $reason !== User::REASON_NOT_REASON) {
//            Yii::$app->rustCheck->ban($this->steam_id, $reasonText);
//        }
//
//        /** @var UserChecking $userChecking */
//        $userChecking = UserChecking::find()
//                             ->andWhere(['user_id' => $this->id])
//                             ->andWhere(['status' => UserChecking::STATUS_CHECKING])
//                             ->one();
//        if (!empty($userChecking)) {
//            $userChecking->status  = UserChecking::STATUS_DONE;
//            $userChecking->done_at = date('Y-m-d H:i:s');
//            $userChecking->save();
//        }

        return true;
    }

    public function unban() {
        $this->ban_by = null;
        $this->ban_reason = null;
        $this->banned_at = null;
        $this->unbanned_at = null;
        $this->status = User::STATUS_ACTIVE;
        $this->save(false);

//        $command = "unban \"{$this->steam_id}\"";
//        RconTasks::execute($command);
//
//        Yii::$app->rustCheck->unban($this->steam_id);
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

    /**
     * @return Servers|string|null
     */
    public function getCurrentServer() {
        if (!empty($this->server)) {
            return $this->server;
        }
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->cache(30)
                          ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        return $servers[0];
    }

    public function getLink($key) {
        if ($key === 'stats') {
            $server = $this->getCurrentServer();
            return "/servers/{$server->tag}/{$this->steam_id}";
        }
        if ($key === 'report') {
            $server = $this->getCurrentServer();
            return "/servers/{$server->tag}/{$this->steam_id}/report";
        }
        if ($key === 'steam') {
            return "https://steamcommunity.com/profiles/{$this->steam_id}";
        }
        return null;
    }

    public function notifications() {
        $supportCount = \common\models\support\Support::unreadAll($this->id);
        return [
            'SUPPORT' => $supportCount,
            'BUILDINGS' => 0,
            'BANS' => 0,
        ];
    }

    public static function searchJS() {
        return [
          'ajaxData' => new JsExpression('function(params) { return {q:params.term}; }'),
          'processResults' => new JsExpression('function (data, params) {return {results: data.items};}'),
          'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
          'templateResult' => new JsExpression("
                                function (repo) {
                                    if (repo.loading) {
                                        return repo.text;
                                    }
                                    var markup =
                                '<a href=\"' + repo.statsLink + '\" class=\"select2_dropdown_item\">' + 
                                    '<div class=\"select2_dropdown_item_image\"><img src=\"' + repo.avatar + '\"/></div>' +
                                    '<div class=\"select2_dropdown_item_content\">' +
                                    '<div class=\"select2_dropdown_item_content_name\">' + repo.name + '</div>' +
                                    '<div class=\"select2_dropdown_item_content_steam_id\">' + repo.steam_id + '</div>' +
                                    '</div>' +
                                '</a>';
                                    return '<div style=\"overflow:hidden;\">' + markup + '</div>';
                                }
                            "),
          'templateSelection' => new JsExpression("
                                    function (repo) {
                                        if (!repo.name) {
                                            return repo.text;
                                        }
                                        return '<div class=\"select2_dropdown_item\">' + 
                                        '<div class=\"select2_dropdown_item_image_24\">' + 
                                            '<img src=\"' + repo.avatar + '\"/></div>' +
                                            '<div class=\"select2_dropdown_item_content\">' +
                                                '<div class=\"select2_dropdown_item_content_name\">' + repo.name + '</div>' +
                                            '</div>' +
                                        '</div>';
                                    }
                            "),
          'eventSelect2' => new JsExpression("
                                    function(e) { 
                                        window.location.href = e.params.data.statsLink;
                                    }
                            "),
        ];
    }

    /**
     * @param       $serverId
     * @param false $update
     *
     * @return array|false|mixed
     */
    public static function getUsers($serverId, $update = false) {
        $cacheKey = "User_getUsers_{$serverId}";
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }
        $date = new \DateTime();
        $date->modify('-30 day');
        /** @var User[] $users */
        $users = User::find()
                     ->andWhere(['>=', 'last_visit_server_at', $date->format('Y-m-d H:i:s')])
                     ->andWhere(['status' => User::STATUS_ACTIVE])
                     ->andWhere(['server_id' => $serverId])
                     ->andWhere(['is_stats' => true])
                     ->orderBy(['last_visit_server_at' => SORT_DESC])
                     ->all();

        $items = [];
        foreach ($users as $user) {
            $items[] = [
                'id' => $user->id,
                'name' => $user->username,
                'strtolower' => mb_strtolower($user->username),
                'steam_id' => $user->steam_id,
                'statsLink' => $user->getLink('stats'),
                'avatar' => $user->getAvatar(),
                'status' => $user->getStatus(),
            ];
        }

        Yii::$app->cache->set($cacheKey, $items, 7*24*60*60);
        return $items;
    }
}
