<?php

namespace common\forms\user;

use common\components\queue\calculating\AggregateDataUserReferralJob;
use common\components\telegram\TelegramDevBot;
use common\components\telegram\TelegramForeignBot;
use common\components\telegram\TelegramPersonalBot;
use common\models\user\UserAnalyticEvent;
use common\models\user\UserBlogger;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use common\components\widgets\phoneInput\PhoneInputValidator;
use common\components\base\Model;
use common\components\helpers\Role;
use common\components\mail\event\Mailer;
use common\components\web\Cookie;
use common\components\web\GeoIp;
use common\models\user\User;
use common\models\user\UserTree;
use common\models\user\UserProfile;
use common\models\user\UserSourceData;
use common\models\user\UserConfirmCode;
use common\models\country\Country;
use common\models\userInvestor\UserInvestor;
use common\models\confirm\ConfirmCode;

class RegistrationForm extends Model
{
    const SCENARIO_FULL      = 'full';
    const SCENARIO_EDUCATION = 'education';
    const SCENARIO_WEALTH    = 'wealth';
    const SCENARIO_WEFI      = 'wefi';
    const SCENARIO_TRANSLATE = 'translate';
    const SCENARIO_OAUTH     = 'oAuth';
    const SCENARIO_FULL_PRO     = 'full_pro';
    const DEFAULT_PARENT_USER_ID = 9;
    const SCENARIO_FULL_CAPTCHA  = 'full_captcha';

    public $email;
    public $name;
    public $surname;
    public $phone;
    public $phoneConfirm;
    public $refCode;
    public $confirmRules;
    public $checkForm;
    public $phoneCode;
    public $registrationCaptcha;
    public $password;

    protected $_parentUserId;

    /**
     * RegistrationForm constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->_setRefCode();
    }

    private function _setRefCode()
    {
        if (!empty($this->refCode)) {
            return;
        }

        $refCode = Cookie::getValue('priorityRefCode');
        if (empty($refCode)) {
            $refCode = Yii::$app->request->get('refCode');
            if (empty($refCode)) {
                $refCode = Cookie::getValue('refCode');
            }
        }

        $this->refCode = $refCode;
    }

    /**
     * @return array
     */
    public function scenarios()
    {
        $commonAttributes = [
            'email',
            'refCode',
            'password',
            'confirmRules',
            'checkForm',
            'phone',
        ];

        return ArrayHelper::merge(parent::scenarios(), [
            self::SCENARIO_FULL      => $commonAttributes,
            self::SCENARIO_FULL_CAPTCHA => ArrayHelper::merge($commonAttributes, ['registrationCaptcha']),
            self::SCENARIO_EDUCATION => $commonAttributes,
            self::SCENARIO_WEALTH    => $commonAttributes,
            self::SCENARIO_TRANSLATE => $commonAttributes,
            self::SCENARIO_WEFI      => $commonAttributes,
            self::SCENARIO_OAUTH     => [
                'email',
                'name',
                'surname',
                'refCode',
            ],
            self::SCENARIO_FULL_PRO      => [
                'email',
                'refCode',
                'confirmRules',
                'checkForm',
                'registrationCaptcha'
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['email', 'name', 'surname', 'phone', 'refCode', 'password'], 'trim'],
            [['email', 'refCode', 'password', 'phone', 'registrationCaptcha'], 'required'],
            [['name', 'surname', 'password'], 'string', 'max' => 255],
            [['phone'], 'string'],
            [['phone'], PhoneInputValidator::class],
            [['email'], 'email'],
            [['refCode', 'phoneCode'], 'number'],
            [['refCode'], 'string', 'min' => 9],
            [['email'], 'string', 'max' => 50],
            [
                'phone',
                'unique',
                'targetClass'     => UserProfile::class,
                'targetAttribute' => 'phone',
                'filter'          => function ($query) {
                    $query->joinWith('user u');
                    $query->andWhere(['u.status' => User::STATUS_ACTIVE]);
                },
            ],
            [
                'confirmRules',
                'required',
                'requiredValue' => 1,
                'message'       => Yii::t('common', 'Согласие с правилами обязательно'),
            ],
            [['phone'], 'validatePhone']
        ];
    }

    public function beforeValidate()
    {
        parent::beforeValidate();

        if (empty($this->refCode)) {
            if (!in_array($this->scenario, [self::SCENARIO_WEFI, self::SCENARIO_EDUCATION, self::SCENARIO_TRANSLATE])) {
                return true;
            }

            if ($this->scenario == self::SCENARIO_WEFI) {
                // @TODO Реф.код info@wefi.pro
                $this->refCode = '3798654588';

            } elseif ($this->scenario == self::SCENARIO_EDUCATION) {
                // @TODO Реф.код digiu0004@gmail.com
                $this->refCode = '1433840649';

            } elseif ($this->scenario == self::SCENARIO_TRANSLATE) {
                // @TODO Реф.код elena.bezborodova@gmail.com
                $this->refCode = '1179931418';
            }
        }

        $parentUser = User::findByRefCode($this->refCode);

        if ($parentUser && $parentUser->id == 919) {
            $this->addError('refCode',
                Yii::t('common', 'Указанный партнер временно недоступен для регистрации'));

        } else {
            if ($parentUser && $parentUser->id != 1) {
                $this->_parentUserId = $parentUser->id;

            } else {
                $this->addError('refCode',
                    Yii::t('common', 'Указанный партнерский код не найден в системе'));
            }
        }

        if($this->phone){
            $userProfile = UserProfile::findByPhone($this->phone);
            if ($userProfile !== null) {
                $this->addError('phone', Yii::t('common', 'Введенный телефон уже зарегистрирован в системе'));
            }
        }

        return true;
    }

    public function attributeLabels()
    {
        return [
            'email'        => Yii::t('common', 'E-mail'),
            'name'         => Yii::t('common', 'Имя'),
            'surname'      => Yii::t('common', 'Фамилия'),
            'password'     => Yii::t('common', 'Пароль'),
            'phone'        => Yii::t('common', 'Телефон'),
            'refCode'      => Yii::t('common', 'Партнерский код'),
            'confirmRules' => Yii::t('common', 'Согласие с правилами системы'),
            'registrationCaptcha' => Yii::t('common', 'Капча'),
        ];
    }


    public function validatePhone()
    {
        $confirmed = ConfirmCode::find()
            ->andWhere(['contact' => $this->phone])
            ->andWhere(['status' => ConfirmCode::STATUS_CONFIRMED])
            ->andWhere(['type' => ConfirmCode::TYPE_CONFIRM_PHONE])
            ->exists();

        if(!$confirmed){
            $this->addError('phone', Yii::t('common', 'Телефон не подтвержден!'));
            return;
        }
        $this->phoneConfirm = true;
    }

    /**
     * @return User|false
     */
    public function register()
    {
        if (!$this->validate()) {
            return false;
        }

        $dbTransaction = Yii::$app->db->beginTransaction();

        try {
            $user = User::findByEmail($this->email, false);

            if (!empty($user)) {
                if ($user->status == User::STATUS_BLOCKED || $user->status == User::STATUS_TMP_BLOCKED) {
                    throw new \Exception(Yii::t('common', 'Введенный E-mail заблокирован в системе'));
                }

                if ($user->status == User::STATUS_ACTIVE) {
                    throw new \Exception(Yii::t('common', 'Введенный E-mail уже зарегистрирован в системе'));
                }

            } else {
                $user = new User();

                $source = User::SOURCE_DEFAULT;
                if ($this->scenario == self::SCENARIO_EDUCATION) {
                    $source = User::SOURCE_EDUCATION;
                } elseif ($this->scenario == self::SCENARIO_WEALTH) {
                    $source = User::SOURCE_WEALTH;
                } elseif ($this->scenario == self::SCENARIO_TRANSLATE) {
                    $source = User::SOURCE_TRANSLATE;
                } elseif ($this->scenario == self::SCENARIO_WEFI) {
                    $source = User::SOURCE_WEFI;
                }

                $user->source = $source;
            }

            $isNewRecord = $user->isNewRecord;

            $date = date('Y-m-d H:i:s');
            $user->status              = User::STATUS_ACTIVE;
            $user->date_activation     = $date;
            $user->date_one_time_offer = $date;
            $user->email            = $this->email;
            $user->country_id       = $this->_getCountryId();
            $user->current_language = Yii::$app->language;
            if (!empty($this->password)) {
                $user->setPassword($this->password);
            } else {
                $user->setPassword(Yii::$app->security->generateRandomString());
            }
            $user->generateAuthKey();

            if ($isNewRecord) {
                $user->generateRefCode();
                $user->generateSocketRoom();
            }

            if (!$user->save(false)) {
                throw new \Exception('User save error');
            }

            if ($isNewRecord) {
                $this->_setUserRole($user);
                $this->_saveUserTree($user);
                $this->_createUserInvestor($user);
                $this->_createUserProfile($user);
                $userProfileModel = UserProfile::findOne(['user_id' => $user->id]);
                $this->_createUserSourceData($user);

                $user = User::findOne($user->id);
            }

            if ($this->getScenario() != self::SCENARIO_OAUTH) {
                self::sendConfirmEmail($user);
            }

            $dbTransaction->commit();

            if (!Mailer::getInstance($user)->sendAuthData($this->password)) {
                Yii::error('sendAuthData error - ' . $user->id, 'error');
            }
            Yii::$app->queueCalculating->push(new AggregateDataUserReferralJob([
                'userId' => $user->id,
            ]));
            $this->_telegramBotAction($user);
            UserAnalyticEvent::createRecord($user->id, UserAnalyticEvent::EVENT_REGISTER);
            return $user;

        } catch (\Exception $e) {
            $dbTransaction->rollBack();

            $this->addError('email', $e->getMessage());
        }

        return false;
    }

    /**
     * @return int|null
     */
    private function _getCountryId()
    {
        $countryCode = GeoIp::getCountry();

        $country = Country::getIdByCode($countryCode);

        return $country ?: 170;
    }

    /**
     * @param User $user
     *
     * @throws \Exception
     */
    private function _setUserRole(User $user)
    {
        $role = Yii::$app->authManager->getRole(Role::ROLE_USER);
        Yii::$app->authManager->assign($role, $user->id);
    }

    /**
     * @param User $user
     */
    private function _saveUserTree(User $user)
    {
        if ($user->id == 1) {
            return;
        }

        if (!UserTree::appendUser($user->id, $this->_parentUserId)) {
            throw new \Exception('Error save user tree');
        }
    }

    /**
     * @param User $user
     */
    private function _createUserProfile(User $user)
    {
        if (!UserProfile::createModel($user, $this->name, $this->surname, $this->phone)) {
            throw new \Exception('Error save user profile');
        }
    }

    /**
     * @param User $user
     */
    private function _createUserInvestor(User $user)
    {
        if (!UserInvestor::createModel($user->id)) {
            throw new \Exception('Error save user investor');
        }
    }

    /**
     * @param User $user
     */
    private function _createUserSourceData(User $user)
    {
        $utmSource   = Yii::$app->session->getFlash('utm_source');
        $utmPost     = Yii::$app->session->getFlash('utm_post');
        $utmTelegram = Yii::$app->session->getFlash('utm_telegram_id');

        if (!empty($utmSource) || !empty($utmPost) || !empty($utmTelegram)) {
            UserSourceData::createRecord($user->id, $utmSource, $utmPost, $utmTelegram);
        }
    }

    /**
     * @param User $user
     *
     * @return bool
     */
    public static function sendConfirmEmail($user)
    {
        if (!Mailer::getInstance($user)->sendConfirmation()) {
            throw new \Exception(Yii::t('common', 'Не удалось отправить ссылку на подтверждение'));
        }

        return true;
    }

    /**
     * @param User $user
     */
    private function _telegramBotAction(User $user)
    {
        $userSource = $user->userSourceData;
        $utmString  = null;
        if (!empty($userSource)) {
            $utmString = 'Utm: ' . $userSource->utm_source . ':' . $userSource->utm_post;
        }

        $attributes = [
            'email'    => $user->email,
            'refCode'  => $user->ref_code,
            'country'  => $user->country->name,
            'level'    => $user->userTree->level,
            'refEmail' => $user->getParentUser()->email,
            'fullName' => $user->userProfile->full_name,
            'utmData'  => $utmString,
        ];

        TelegramDevBot::sendAction(TelegramDevBot::NEW_USER, $attributes);

        $parentUsers = $user->getParentUsers(15);

        foreach ($parentUsers as $i => $parentUser) {
            $attributes['level'] = $i + 1;

            TelegramPersonalBot::sendAction($parentUser->id, TelegramPersonalBot::NEW_REFERRAL, $attributes);
        }

        TelegramForeignBot::sendEvent('register', $user);
    }
}