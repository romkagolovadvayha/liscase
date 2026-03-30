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
    public $recipientUserId;
    public $amount;

    /** @var User */
    public $sender;

    public function attributeLabels()
    {
        return [
            'recipientUserId' => 'ID получателя',
            'amount' => 'Сумма (₽)',
        ];
    }

    public function rules()
    {
        return [
            [['recipientUserId', 'amount', 'sender'], 'required'],
            [['recipientUserId'], 'integer', 'min' => 1],
            [['amount'], 'number', 'min' => 0.01],
            [['recipientUserId'], 'validateRecipient'],
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
        }
    }

    public function validateRecipient($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }
        $rid = (int)$this->recipientUserId;
        if ($rid === (int)$this->sender->id) {
            $this->addError($attribute, 'Получатель не может совпадать с отправителем.');
            return;
        }
        if (!User::find()->andWhere(['id' => $rid])->exists()) {
            $this->addError($attribute, 'Пользователь с таким ID не найден.');
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
        $recipient = User::findOne((int)$this->recipientUserId);
        if (!$recipient) {
            $this->addError('recipientUserId', 'Пользователь не найден.');
            return false;
        }

        $toBalance = $recipient->getPersonalBalance();

        $amt = round((float)$this->amount, 2);
        $commentOut = sprintf(
            'Перевод пользователю %d (%s)',
            $recipient->id,
            $recipient->username
        );
        $commentIn = sprintf(
            'Перевод от пользователя %d (%s)',
            $this->sender->id,
            $this->sender->username
        );

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            // Списание: лицевой счёт считается как sum(profit)+deposits−sum(invoice), не unsigned profit
            $invoice = new Invoice();
            $invoice->user_id = $this->sender->id;
            $invoice->type = Invoice::TYPE_ADMIN_TRANSFER;
            $invoice->amount = $amt;
            $invoice->box_id = 0;
            $invoice->sets_id = 0;
            $invoice->drop_id = 0;
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

            // Явный пересчёт у обоих (после commit; свежие строки из БД, не статический кэш getBalance)
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
