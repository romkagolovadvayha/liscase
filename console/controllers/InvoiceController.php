<?php

namespace console\controllers;

use common\components\queue\process\ActivatedDropJob;
use common\models\invoice\Invoice;
use common\models\user\UserDrop;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Разовые операции по таблице invoice.
 */
class InvoiceController extends Controller
{
    /**
     * Invoice с amount = 0 и type = маркет (2): найти связанные user_drop и выставить статус «отправлен» (получен).
     * Учитываются только invoice за последние 2 суток (по created_at).
     *
     * Без аргумента — только список действий. С аргументом 1 — запись в БД.
     *
     * Примеры:
     *   php yii invoice/fix-zero-market-drops
     *   php yii invoice/fix-zero-market-drops 1
     *
     * @param int|string $apply Передайте 1, чтобы применить изменения
     * @return int
     */
    public function actionFixZeroMarketDrops($apply = 0): int
    {
        $doApply = (int) $apply === 1;

        $since = date('Y-m-d H:i:s', strtotime('-2 days'));

        $invoices = Invoice::find()
            ->where(['type' => Invoice::TYPE_PAYMENT_MARKET_DROP])
            ->andWhere('[[amount]] = 0')
            ->andWhere(['IS NOT', 'drop_id', null])
            ->andWhere(['>', 'drop_id', 0])
            ->andWhere(['>=', 'created_at', $since])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if (empty($invoices)) {
            $this->stdout("Подходящих invoice не найдено.\n");
            return ExitCode::OK;
        }

        $this->stdout($doApply ? "РЕЖИМ ПРИМЕНЕНИЯ\n" : "Просмотр (без изменений). Для применения: php yii invoice/fix-zero-market-drops 1\n");
        $this->stdout('Фильтр: invoice.created_at >= ' . $since . " (последние 2 суток)\n");
        $this->stdout('Найдено invoice: ' . count($invoices) . "\n\n");

        $updated = 0;
        $skipped = 0;
        $plannedRows = 0;
        $touchedUserDropIds = [];
        $tx = $doApply ? Yii::$app->db->beginTransaction() : null;

        try {
            foreach ($invoices as $invoice) {
                /** @var Invoice $invoice */
                $userDrops = $this->findUserDropsForInvoice($invoice);
                $statusList = UserDrop::getStatusList();

                if (empty($userDrops)) {
                    $this->stdout(sprintf(
                        "[SKIP] invoice id=%d user_id=%d drop_id=%s created=%s — нет подходящего user_drop (ACTIVE/WAIT)\n",
                        $invoice->id,
                        $invoice->user_id,
                        $invoice->drop_id,
                        $invoice->created_at
                    ));
                    $skipped++;
                    continue;
                }

                foreach ($userDrops as $userDrop) {
                    if ($doApply && isset($touchedUserDropIds[$userDrop->id])) {
                        $this->stdout(sprintf(
                            "[SKIP] invoice id=%d — user_drop id=%d уже обновлён в этом запуске\n",
                            $invoice->id,
                            $userDrop->id
                        ));
                        continue;
                    }

                    $oldStatus = (int) $userDrop->status;
                    $oldLabel = $statusList[$oldStatus] ?? (string) $oldStatus;

                    $this->stdout(sprintf(
                        "%s invoice id=%d → user_drop id=%d user_id=%d drop_id=%d status: %s → %s\n",
                        $doApply ? '[APPLY]' : '[PLAN]',
                        $invoice->id,
                        $userDrop->id,
                        $userDrop->user_id,
                        $userDrop->drop_id,
                        $oldLabel,
                        $statusList[UserDrop::STATUS_SENDED] ?? 'SENDED'
                    ));

                    if ($doApply) {
                        $userDrop->status = UserDrop::STATUS_SENDED;
                        $userDrop->sended_at = date('Y-m-d H:i:s');
                        if (!$userDrop->save(false)) {
                            throw new \RuntimeException('user_drop id=' . $userDrop->id . ' save failed');
                        }
                        if (Yii::$app->has('queueProcess')) {
                            Yii::$app->queueProcess->push(new ActivatedDropJob(['userDrop' => $userDrop]));
                        }
                        $touchedUserDropIds[$userDrop->id] = true;
                        $updated++;
                    } else {
                        $plannedRows++;
                    }
                }
            }

            if ($tx !== null) {
                $tx->commit();
            }
        } catch (\Throwable $e) {
            if ($tx !== null) {
                $tx->rollBack();
            }
            $this->stderr('Ошибка: ' . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nГотово. ");
        if ($doApply) {
            $this->stdout("Обновлено записей user_drop: {$updated}. Пропущено invoice: {$skipped}.\n");
        } else {
            $this->stdout("Строк user_drop к обновлению: {$plannedRows}. Пропущено invoice: {$skipped}.\n");
        }

        return ExitCode::OK;
    }

    /**
     * @return UserDrop[]
     */
    private function findUserDropsForInvoice(Invoice $invoice): array
    {
        $dropId = (int) $invoice->drop_id;
        $t0 = strtotime($invoice->created_at);
        if ($t0 === false) {
            $t0 = time();
        }
        $from = date('Y-m-d H:i:s', $t0 - 10);
        $to = date('Y-m-d H:i:s', $t0 + 120);

        $rows = UserDrop::find()
            ->where(['user_id' => $invoice->user_id])
            ->andWhere(['in', 'status', [UserDrop::STATUS_ACTIVE, UserDrop::STATUS_WAIT]])
            ->andWhere(['between', 'created_at', $from, $to])
            ->andWhere([
                'or',
                ['drop_id' => $dropId],
                ['parent_drop_id' => $dropId],
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if (!empty($rows)) {
            return $rows;
        }

        $candidates = UserDrop::find()
            ->where(['user_id' => $invoice->user_id])
            ->andWhere(['in', 'status', [UserDrop::STATUS_ACTIVE, UserDrop::STATUS_WAIT]])
            ->andWhere([
                'or',
                ['drop_id' => $dropId],
                ['parent_drop_id' => $dropId],
            ])
            ->all();

        if (empty($candidates)) {
            return [];
        }

        usort($candidates, static function (UserDrop $a, UserDrop $b) use ($t0): int {
            $da = abs(strtotime($a->created_at) - $t0);
            $db = abs(strtotime($b->created_at) - $t0);
            if ($da === $db) {
                return $a->id <=> $b->id;
            }
            return $da <=> $db;
        });

        return [$candidates[0]];
    }
}
