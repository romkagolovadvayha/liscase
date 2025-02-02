<?php

namespace frontend\forms\user;

use common\components\web\Cookie;
use common\models\profit\Profit;
use common\models\promocode\Promocode;
use common\models\user\User;
use common\models\user\UserPayoutReferral;
use common\models\user\UserPayoutSkins;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;

class TransferForm extends \common\components\base\ActiveRecord
{

    const TYPE_REFERRAL = 'referral';
    const TYPE_SKINS = 'skins';

    public string $type = 'referral';
    public int $amount = 0;

    public function rules(): array
    {
        return [
            [['type', 'amount'], 'required'],
            [['type'], 'string'],
            [['amount'], 'integer', 'min' => 1],
        ];
    }

    /**
     * @return bool|\yii\db\ActiveRecord|null
     */
    public function saveRecord()
    {
        if (Yii::$app->user->isGuest) {
            $this->addError('code', Yii::t('common', 'Вам нужно авторизоваться на сайте!'));
            return null;
        }
        if ($this->amount <= 0) {
            $this->addError('type', Yii::t('common', 'Неверная сумма перевода!'));
            return null;
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        if ($this->type === self::TYPE_REFERRAL) {
            $balance = $user->getReferralBalance();
            if ($this->amount > $balance) {
                $this->addError('type', Yii::t('common', 'У вас на балансе нет средств для перевода!'));
                return null;
            }

            $dbTransaction = Yii::$app->db->beginTransaction();
            try {
                $userPayoutReferral = new UserPayoutReferral();
                $userPayoutReferral->user_id  = $user->id;
                $userPayoutReferral->amount  = $this->amount;
                $userPayoutReferral->created_at = date('Y-m-d H:i:s');

                $profit = new Profit();
                $profit->status = 1;
                $profit->type = Profit::TYPE_TRANSFER_REFERRAL;
                $profit->amount = ceil($this->amount);
                $profit->user_balance_id = $user->getPersonalBalance()->id;
                $profit->comment = Yii::t('common', 'Перевод из реферальной системы');
                $profit->created_at = date('Y-m-d H:i:s');

                if ($userPayoutReferral->save() && $profit->save(false)) {
                    $user->getPersonalBalance()->recalculateBalance();
                    $dbTransaction->commit();
                } else {
                    $dbTransaction->rollBack();
                }
            } catch (\Exception $e) {
                $dbTransaction->rollBack();
                $this->addError('type', Yii::t('common', 'Произошла ошибка при переводе средств!'));
            }

            return ceil($balance);
        }
        if ($this->type === self::TYPE_SKINS) {
            $balance = $user->getSkinsBalance()->balance;
            if ($this->amount > $balance) {
                $this->addError('type', Yii::t('common', 'У вас на балансе нет средств для перевода!'));
                return null;
            }

            $dbTransaction = Yii::$app->db->beginTransaction();
            try {
                $profit = new Profit();
                $profit->status = 1;
                $profit->type = Profit::TYPE_TRANSFER_SKINS;
                $profit->amount = ceil($this->amount);
                $profit->user_balance_id = $user->getPersonalBalance()->id;
                $profit->comment = Yii::t('common', 'Перевод из вывода скинов');
                $profit->created_at = date('Y-m-d H:i:s');

                if ($profit->save(false)) {
                    $user->getPersonalBalance()->recalculateBalance();
                    $user->getSkinsBalance()->recalculateBalance();
                    $dbTransaction->commit();
                } else {
                    $dbTransaction->rollBack();
                }
            } catch (\Exception $e) {
                $dbTransaction->rollBack();
                $this->addError('type', Yii::t('common', 'Произошла ошибка при переводе средств!'));
            }

            return ceil($this->amount);
        }

        return null;
    }

}
