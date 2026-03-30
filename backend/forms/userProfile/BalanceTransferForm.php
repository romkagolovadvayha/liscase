<?php

namespace backend\forms\userProfile;

use common\models\invoice\Invoice;
use common\models\profit\Profit;
use common\models\user\User;
use common\models\user\UserBalance;
use Yii;
use yii\base\Model;

class BalanceTransferForm extends Model
{
    public $recipientSteamId;
    public $amount;

    /** @var User */
    public $sender;

    public function attributeLabels()
    {
        return [
            'recipientSteamId' => 'Steam ID получателя',
            'amount' => 'Сумма (₽)',
        ];
    }

    public function rules()
    {
        return [
            [['recipientSteamId', 'amount', 'sender'], 'required'],
            [['recipientSteamId'], 'trim'],
            [['recipientSteamId'], 'match', 'pattern' => '/^\d{8,20}$/', 'message' => 'Укажите корректный Steam ID (только цифры).'],
            [['amount'], 'number', 'min' => 0.01],
            [['recipientSteamId'], 'validateRecipient'],
            [['amount'], 'validateAmount'],
        ];
    }

    public function setUserId($userId)
    {
        if (empty($userId)) {
            return;
        }
        $this->sender = User::findOne($userId);
        if (empty($this->sender)) {
            $this->addError('formError', 'Пользователь не найден');
            return;
        }
        if ($this->amount === null || $this->amount === '') {
            $b = $this->sender->getPersonalBalance();
            if ($b) {
                $this->amount = round((float)$b->balance, 2);
            }
        }
    }

    public function validateRecipient($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }
        $sid = (string)$this->recipientSteamId;
        if ($sid === (string)$this->sender->steam_id) {
            $this->addError($attribute, 'Получатель не может совпадать с отправителем.');
            return;
        }
        if (!User::find()->andWhere(['steam_id' => $sid])->exists()) {
            $this->addError($attribute, 'Пользователь с таким Steam ID не найден.');
        }
    }

    public function validateAmount($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }
        $balance = $this->sender->getPersonalBalance();
        $amt = round((float)$this->amount, 2);
        $available = round((float)$balance->balance, 2);
        if ($amt > $available + 1e-6) {
            $this->addError($attribute, 'Недостаточно средств на лицевом счёте.');
        }
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $recipient = User::find()->where(['steam_id' => (string)$this->recipientSteamId])->one();
        if (!$recipient) {
            $this->addError('recipientSteamId', 'Пользователь не найден.');
            return false;
        }

        $toBalance = $recipient->getPersonalBalance();

        $amt = round((float)$this->amount, 2);
        $commentOut = sprintf(
            'Перевод пользователю %d (%s), steam %s',
            $recipient->id,
            $recipient->username,
            $recipient->steam_id
        );
        $commentIn = sprintf(
            'Перевод от пользователя %d (%s), steam %s',
            $this->sender->id,
            $this->sender->username,
            $this->sender->steam_id
        );

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $invoice = new Invoice();
            $invoice->user_id = $this->sender->id;
            $invoice->type = Invoice::TYPE_ADMIN_TRANSFER;
            $invoice->amount = $amt;
            $invoice->box_id = null;
            $invoice->sets_id = null;
            $invoice->drop_id = null;
            $invoice->comment = $commentOut;
            $invoice->created_at = date('Y-m-d H:i:s');
            if (!$invoice->save(false)) {
                $transaction->rollBack();
                $this->addError('formError', 'Не удалось сохранить списание.');
                return false;
            }

            $in = new Profit();
            $in->user_balance_id = $toBalance->id;
            $in->amount = $amt;
            $in->type = Profit::TYPE_TRANSFER_BALANCE;
            $in->comment = $commentIn;
            $in->status = 1;
            $in->created_at = date('Y-m-d H:i:s');
            if (!$in->save(false)) {
                $transaction->rollBack();
                $this->addError('formError', 'Не удалось сохранить зачисление.');
                return false;
            }

            $transaction->commit();

            $senderBalance = UserBalance::findOne([
                'user_id' => $this->sender->id,
                'type' => UserBalance::TYPE_PERSONAL,
            ]);
            if ($senderBalance) {
                $senderBalance->recalculateBalance();
            }
            $recipientBalance = UserBalance::findOne([
                'user_id' => $recipient->id,
                'type' => UserBalance::TYPE_PERSONAL,
            ]);
            if ($recipientBalance) {
                $recipientBalance->recalculateBalance();
            }

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            $this->addError('formError', 'Ошибка перевода.');
            return false;
        }
    }
}
