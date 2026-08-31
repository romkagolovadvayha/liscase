<?php

namespace console\controllers;

use common\models\invoice\Deposit;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\skindrops\SkindropsLink;
use common\models\stats\Info;
use common\models\user\UserBalance;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\db\Query;

class DepositController extends Controller
{
    /**
     * Чекалка депозитов
     * deposit/sync
     *
     * @throws \Exception
     */
    public function actionSync()
    {
        /** @var Deposit[] $deposits */
        $deposits = Deposit::find()
            ->andWhere(['status' => Deposit::STATUS_WAIT_CONFIRM])
            ->andWhere('payment_id is not null')
            ->andWhere(['NOT IN', 'payment_type', [Deposit::TYPE_PAYMENT_CARD_TINKOFF]])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        foreach ($deposits as $deposit) {
            $deposit->check();
        }
    }

    /**
     * Reports legacy duplicate top-up bonuses without changing them.
     *
     * Historical duplicate awards are intentionally retained. Future bonuses
     * are protected separately by the unique profit.deposit_id constraint and
     * the atomic Deposit::markSuccessful() transition.
     *
     * Usage: yii deposit/audit-bonuses
     */
    public function actionAuditBonuses()
    {
        $groups = (new Query())
            ->select([
                'user_balance_id',
                'amount',
                'created_at',
                'copies' => new \yii\db\Expression('COUNT(*)'),
                'profit_ids' => new \yii\db\Expression('GROUP_CONCAT(id ORDER BY id)'),
            ])
            ->from(Profit::tableName())
            ->where([
                'type' => Profit::TYPE_BONUS,
                'comment' => 'Бонус при пополнении',
                'deposit_id' => null,
            ])
            ->groupBy(['user_balance_id', 'amount', 'created_at'])
            ->having(['>', new \yii\db\Expression('COUNT(*)'), 1])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $matchedGroups = 0;
        $ambiguousGroups = 0;

        foreach ($groups as $group) {
            $balance = UserBalance::findOne((int)$group['user_balance_id']);
            if (!$balance) {
                $ambiguousGroups++;
                continue;
            }

            $deposits = Deposit::find()
                ->andWhere([
                    'user_id' => (int)$balance->user_id,
                    'status' => Deposit::STATUS_SUCCESS,
                    'completed_at' => $group['created_at'],
                ])
                ->all();
            $matchingDeposits = array_filter($deposits, static function (Deposit $deposit) use ($group) {
                return Deposit::calculateBonusAmount((int)$deposit->amount) === (int)$group['amount'];
            });

            if (count($matchingDeposits) !== 1) {
                $ambiguousGroups++;
                $this->stdout(sprintf(
                    "AMBIGUOUS balance=%d amount=%s time=%s profits=%s deposits=%d\n",
                    (int)$group['user_balance_id'],
                    $group['amount'],
                    $group['created_at'],
                    $group['profit_ids'],
                    count($matchingDeposits)
                ));
                continue;
            }

            $matchedGroups++;
            $deposit = reset($matchingDeposits);
            $this->stdout(sprintf(
                "DUPLICATE deposit=%d balance=%d amount=%s time=%s profits=%s\n",
                (int)$deposit->id,
                (int)$group['user_balance_id'],
                $group['amount'],
                $group['created_at'],
                $group['profit_ids']
            ));
        }

        $this->stdout(sprintf(
            "Summary: groups=%d matched=%d ambiguous=%d mode=report-only\n",
            count($groups),
            $matchedGroups,
            $ambiguousGroups
        ));

        return self::EXIT_CODE_NORMAL;
    }
}
