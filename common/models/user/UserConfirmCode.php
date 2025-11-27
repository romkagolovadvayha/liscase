<?php

namespace common\models\user;

use Yii;

/**
 * This is the model class for table "user_confirm_code".
 *
 * @property int         $id
 * @property int         $user_id
 * @property int         $type
 * @property string      $code
 * @property int         $status
 * @property string|null $created_at
 *
 * @property User        $user
 */
class UserConfirmCode extends \common\components\base\ActiveRecord
{
    const STATUS_ACTIVE   = 1;
    const STATUS_DISABLED = 0;

    const TYPE_CONFIRM_EMAIL     = 1;
    const TYPE_RESET_PASSWORD    = 2;
    const TYPE_CONFIRM_PHONE     = 3;
    const TYPE_TELEGRAM_BOT      = 4;
    const TYPE_FINANCE_OPERATION = 5;
    const TYPE_CONFIRM_NFT_EYWA_WALLET  = 6;
    const TYPE_VK_GROUP          = 7;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_confirm_code';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'type', 'code'], 'required'],
            [['user_id', 'type', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['code'], 'string', 'max' => 64],
        ];
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

    /**
     * @param int $userId
     *
     * @return self|bool
     */
    public static function createTypeEmail($userId)
    {
        $type = self::TYPE_CONFIRM_EMAIL;
        $code = Yii::$app->security->generateRandomString();

        return self::_createRecord($userId, $type, $code);
    }

    /**
     * @param int $userId
     *
     * @return self|bool
     */
    public static function createTypePassword($userId)
    {
        $type = self::TYPE_RESET_PASSWORD;
        $code = Yii::$app->security->generateRandomString() . '_' . time();

        return self::_createRecord($userId, $type, $code);
    }

    /**
     * @param int $userId
     *
     * @return self|false
     */
    public static function createTypeTelegramBot($userId)
    {
        $type = self::TYPE_TELEGRAM_BOT;

        /** @var self $model */
        $model = self::_getQuery($userId, $type)->one();
        if (!empty($model)) {
            return $model;
        }

        $code = Yii::$app->security->generateRandomString(20);

        return self::_createRecord($userId, $type, $code);
    }

    /**
     * @param $userId
     * @param $code
     * @param string $typeConfirm
     * @param string $providerService
     * @return bool|UserConfirmCode
     */
    public static function createTypePhone($userId, $code, string $typeConfirm='', string $providerService='')
    {
        if (empty($code)) {
            return false;
        }

        return self::_createRecord($userId, self::TYPE_CONFIRM_PHONE, $code, $typeConfirm, $providerService);
    }

    /**
     * @param int $userId
     *
     * @return self|bool
     */
    public static function createTypeFinanceOperation($userId)
    {
        $code = (string)(time() * rand(1, 10000000));
        $code = substr($code, 0, 6);

        return self::_createRecord($userId, self::TYPE_FINANCE_OPERATION, $code);
    }

    /**
     * @param $userId
     * @param $type
     * @param $code
     * @return UserConfirmCode|false
     */
    private static function _createRecord($userId, $type, $code)
    {
        if (empty($userId) || empty($type) || empty($code)) {
            return false;
        }

        self::updateStatus($userId, $type);

        $model = new self();

        $model->user_id = $userId;
        $model->status  = self::STATUS_ACTIVE;
        $model->type    = $type;
        $model->code    = (string)$code;
        $result = $model->save() ? $model : false;
        return $result;
    }

    /**
     * @return bool
     */
    public function isPasswordResetTokenValid()
    {
        $token = $this->code;

        $timestamp = (int)substr($token, strrpos($token, '_') + 1);
        $expire    = 3600;

        return $timestamp + $expire >= time();
    }

    /**
     * @param string $code
     *
     * @return User|null
     */
    public static function getUserByPasswordResetCode($code)
    {
        /** @var self $model */
        $model = self::find()
            ->andWhere(['type' => self::TYPE_RESET_PASSWORD])
            ->andWhere(['code' => $code])
            ->one();

        if (empty($model) || !$model->isPasswordResetTokenValid()) {
            return null;
        }

        return $model->user;
    }

    /**
     * @param string $code
     *
     * @return User|null
     */
    public static function getUserByTelegramCode($code)
    {
        /** @var self $model */
        $model = self::find()
            ->andWhere(['type' => self::TYPE_TELEGRAM_BOT])
            ->andWhere(['code' => $code])
//            ->andWhere(['status' => self::STATUS_ACTIVE])
            ->one();

        return $model ? $model->user : null;
    }

    /**
     * @param int $userId
     *
     * @return self|false
     */
    public static function createTypeVkGroup($userId)
    {
        $type = self::TYPE_VK_GROUP;

        /** @var self $model */
        $model = self::_getQuery($userId, $type)->one();
        if (!empty($model)) {
            return $model;
        }

        $code = Yii::$app->security->generateRandomString(20);

        return self::_createRecord($userId, $type, $code);
    }

    /**
     * @param string $code
     *
     * @return User|null
     */
    public static function getUserByVkCode($code)
    {
        /** @var self $model */
        $model = self::find()
            ->andWhere(['type' => self::TYPE_VK_GROUP])
            ->andWhere(['code' => $code])
            ->andWhere(['status' => self::STATUS_ACTIVE])
            ->one();

        return $model ? $model->user : null;
    }

    /**
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public static function checkUserConfirmNftEywaWallet($userId, $code)
    {
        return self::find()
                   ->andWhere(['user_id' => $userId])
                   ->andWhere(['type' => self::TYPE_CONFIRM_NFT_EYWA_WALLET])
                   ->andWhere(['code' => $code])
                   ->andWhere(['status' => self::STATUS_ACTIVE])
                   ->exists();
    }

    /**
     * @param int $userId
     * @param int $type
     */
    public static function updateStatus($userId, $type)
    {
        /** @var self[] $oldModels */
        $oldModels = self::_getQuery($userId, $type)->all();

        foreach ($oldModels as $oldModel) {
            $oldModel->status = self::STATUS_DISABLED;
            $oldModel->save(false);
        }
    }

    /**
     * @param int    $userId
     * @param string $code
     *
     * @return bool
     */
    public static function checkUserConfirmPhone($userId, $code)
    {
        return self::find()
            ->andWhere(['user_id' => $userId])
            ->andWhere(['type' => self::TYPE_CONFIRM_PHONE])
            ->andWhere(['code' => $code])
            ->exists();
    }

    /**
     * @param int $userId
     *
     * @return self
     */
    public static function getUserConfirmEmail($userId)
    {
        /** @var self $model */
        $model = self::_getQuery($userId, self::TYPE_CONFIRM_EMAIL)->one();

        if (empty($model)) {
            $model = self::createTypeEmail($userId);
        }

        return $model;
    }

    /**
     * @param int $userId
     *
     * @return self
     */
    public static function getUserConfirmNftWallet($userId)
    {
        /** @var self $model */
        $model = self::_getQuery($userId, self::TYPE_CONFIRM_NFT_EYWA_WALLET)->one();

        if (empty($model)) {
            $model = self::createTypeNftWallet($userId);
        }

        return $model;
    }

    /**
     * @param int $userId
     *
     * @return self|bool
     */
    public static function createTypeNftWallet($userId)
    {
        $type = self::TYPE_CONFIRM_NFT_EYWA_WALLET;
        $code = (string)(time() * rand(1, 10000000));
        $code = substr($code, 0, 6);

        return self::_createRecord($userId, $type, $code);
    }

    /**
     * @param int $userId
     *
     * @return string
     */
    public static function getEmailConfirmLink($userId)
    {
        $model = self::getUserConfirmEmail($userId);

        $params = [
            'user' => base64_encode($userId),
            'hash' => $model->code,
        ];

        return Yii::$app->params['baseUrl'] . '/auth/confirm-email?' . http_build_query($params);
    }

    /**
     * @param int $userId
     *
     * @return string
     */
    public static function getNftWalletConfirmLink($userId)
    {
        $model = self::getUserConfirmNftWallet($userId);

        $params = [
            'user' => base64_encode($userId),
            'hash' => $model->code,
        ];

        return Yii::$app->params['baseUrl'] . '/auth/confirm-nft-wallet?' . http_build_query($params);
    }

    /**
     * @param int $userId
     *
     * @return string
     */
    public static function getPasswordResetLink($userId)
    {
        /** @var self $model */
        $model = self::_getQuery($userId, self::TYPE_RESET_PASSWORD)->one();

        if (empty($model) || !$model->isPasswordResetTokenValid()) {
            $model = self::createTypePassword($userId);
        }

        return Yii::$app->params['baseUrl'] . '/auth/reset-password?token=' . $model->code;
    }

    /**
     * @param int $userId
     *
     * @return self
     */
    public static function getFinanceConfirmModel($userId)
    {
        return self::_getQuery($userId, self::TYPE_FINANCE_OPERATION)->one();
    }

    /**
     * @param int $userId
     * @param int $type
     *
     * @return \yii\db\ActiveQuery
     */
    private static function _getQuery($userId, $type)
    {
        return self::find()
            ->andWhere(['user_id' => $userId])
            ->andWhere(['type' => $type])
            ->andWhere(['status' => self::STATUS_ACTIVE]);
    }
}
