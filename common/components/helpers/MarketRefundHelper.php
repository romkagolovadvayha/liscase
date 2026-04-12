<?php

namespace common\components\helpers;

use common\models\box\Drop;
use common\models\invoice\Invoice;
use common\models\profit\Profit;
use common\models\user\User;
use common\models\user\UserBalance;
use common\models\user\UserDrop;
use Yii;

/**
 * Сумма возврата на баланс за строку корзины маркета (UserDrop) и атомарное выполнение возврата.
 *
 * Приоритет суммы: invoice_id + совпадение drop_id с строкой → legacy-инвойс (с потолком) → пересчёт по стеку.
 */
class MarketRefundHelper
{
    /**
     * Сумма к зачислению при возврате одной строки корзины (без записи в БД).
     */
    public static function amountForMarketReturn(User $user, UserDrop $userDrop, Drop $drop): int
    {
        if (!empty($userDrop->invoice_id)) {
            $invoice = Invoice::find()
                ->where([
                    'id' => (int) $userDrop->invoice_id,
                    'user_id' => (int) $user->id,
                    'type' => Invoice::TYPE_PAYMENT_MARKET_DROP,
                ])
                ->one();
            // Инвойс должен относиться к этой строке (тот же market drop_id). Иначе не доверяем привязке.
            if ($invoice !== null && (int) $invoice->drop_id === (int) $userDrop->drop_id) {
                return (int) ceil((float) $invoice->amount);
            }
        }

        $legacy = self::resolveRefundAmountFromLegacyPurchaseInvoice($user, $userDrop);
        $formula = Drop::getRefundAmountForUserDropLine($userDrop, $drop);

        if ($legacy !== null) {
            // Не отдаём больше «эквивалента» строки по текущим ценам — гасит неверный подбор чужого крупного инвойса
            // и накрутку при росте цены после покупки. Если цена админом упала ниже оплаченного, возможен заниженный возврат.
            return min($legacy, $formula);
        }

        return $formula;
    }

    /**
     * Без invoice_id: последний подходящий invoice с тем же drop_id, созданный не позже этой строки корзины.
     */
    public static function resolveRefundAmountFromLegacyPurchaseInvoice(User $user, UserDrop $userDrop): ?int
    {
        $invoice = Invoice::find()
            ->where([
                'user_id' => (int) $user->id,
                'type' => Invoice::TYPE_PAYMENT_MARKET_DROP,
                'drop_id' => (int) $userDrop->drop_id,
            ])
            ->andWhere(['<=', 'created_at', $userDrop->created_at])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->one();

        if ($invoice === null) {
            return null;
        }

        return (int) ceil((float) $invoice->amount);
    }

    /**
     * Атомарный возврат строки корзины: блокировка строки user_drop, проверки, profit, смена статуса.
     *
     * @return array{ok:bool, error?:string, http?:int, userDrop?:UserDrop, refundAmount?:int}
     */
    public static function performMarketCartReturn(User $user, int $userDropId): array
    {
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            /** @var UserDrop|null $locked */
            $locked = UserDrop::findBySql(
                'SELECT * FROM ' . UserDrop::tableName() . ' WHERE [[id]] = :id AND [[user_id]] = :uid FOR UPDATE',
                [':id' => $userDropId, ':uid' => (int) $user->id]
            )->one();

            if ($locked === null) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'ITEM_NOT_FOUND', 'http' => 404];
            }

            if (!empty($locked->box_id) || !empty($locked->sets_id)) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'CANNOT_RETURN', 'http' => 400];
            }

            if (!empty($locked->parent_drop_id)) {
                $parentDrop = Drop::findOne((int) $locked->parent_drop_id);
                if ($parentDrop === null || (int) $parentDrop->drop_type !== Drop::TYPE_SELECT) {
                    $transaction->rollBack();
                    return ['ok' => false, 'error' => 'CANNOT_RETURN', 'http' => 400];
                }
            }

            if ((int) $locked->status !== UserDrop::STATUS_ACTIVE) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'INVALID_STATUS', 'http' => 400];
            }

            if ((int) $locked->count < 1) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'INVALID_COUNT', 'http' => 400];
            }

            $drop = Drop::findOne((int) $locked->drop_id);
            if ($drop === null) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'DROP_NOT_FOUND', 'http' => 404];
            }

            $refundAmount = self::amountForMarketReturn($user, $locked, $drop);
            if ($refundAmount < 1) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'INVALID_REFUND', 'http' => 400];
            }

            /** @var UserBalance $userBalance */
            $userBalance = $user->getPersonalBalance();

            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $refundAmount;
            $profit->user_balance_id = $userBalance->id;
            $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $drop->name),
            ]);
            $profit->created_at = date('Y-m-d H:i:s');

            if (!$profit->save(false)) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'SAVE_ERROR', 'http' => 500];
            }

            $locked->status = UserDrop::STATUS_SELL;
            if (!$locked->save(false)) {
                $transaction->rollBack();
                return ['ok' => false, 'error' => 'SAVE_ERROR', 'http' => 500];
            }

            $transaction->commit();

            return [
                'ok' => true,
                'userDrop' => $locked,
                'refundAmount' => $refundAmount,
            ];
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);

            return ['ok' => false, 'error' => 'EXCEPTION', 'http' => 500];
        }
    }
}
