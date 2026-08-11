<?php

namespace console\controllers;

use common\models\rcon\RconTasks;
use common\models\support\Support;
use common\models\user\UserConfirmCode;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Bounded retention jobs for operational data.
 *
 * Dry-run is the default. Production deletion is enabled explicitly with
 * MAINTENANCE_CLEANUP_APPLY=1, so a newly deployed cron cannot erase data by
 * accident.
 */
class MaintenanceController extends Controller
{
    public int $batchSize = 1000;
    public int $maxBatches = 100;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['batchSize', 'maxBatches']);
    }

    /**
     * yii maintenance/cleanup [--batchSize=1000] [--maxBatches=100]
     */
    public function actionCleanup(): int
    {
        $apply = filter_var((string)getenv('MAINTENANCE_CLEANUP_APPLY'), FILTER_VALIDATE_BOOL);
        $this->batchSize = max(100, min(5000, $this->batchSize));
        $this->maxBatches = max(1, min(1000, $this->maxBatches));

        $this->stdout($apply
            ? "Maintenance cleanup: APPLY mode\n"
            : "Maintenance cleanup: DRY RUN (set MAINTENANCE_CLEANUP_APPLY=1 to delete)\n");

        $jobs = [
            [
                'label' => 'completed RCON tasks older than 30 days',
                'table' => 'rcon_tasks',
                'condition' => 'status = :status AND created_at < :cutoff',
                'params' => [
                    ':status' => RconTasks::STATUS_DONE,
                    ':cutoff' => gmdate('Y-m-d H:i:s', time() - 30 * 86400),
                ],
                'orderBy' => 'created_at ASC, id ASC',
            ],
            [
                'label' => 'used/disabled confirmation codes older than 7 days',
                'table' => 'user_confirm_code',
                'condition' => 'status = :status AND created_at < :cutoff',
                'params' => [
                    ':status' => UserConfirmCode::STATUS_DISABLED,
                    ':cutoff' => gmdate('Y-m-d H:i:s', time() - 7 * 86400),
                ],
                'orderBy' => 'created_at ASC, id ASC',
            ],
            [
                'label' => 'read markers for tickets closed more than 180 days ago',
                'table' => 'support_read',
                'condition' => 'support_id IN (SELECT id FROM support WHERE status = :status AND updated_at < :cutoff)',
                'params' => [
                    ':status' => Support::STATUS_CLOSED,
                    ':cutoff' => gmdate('Y-m-d H:i:s', time() - 180 * 86400),
                ],
                'orderBy' => 'id ASC',
            ],
        ];

        foreach ($jobs as $job) {
            $count = $this->countRows($job['table'], $job['condition'], $job['params']);
            $this->stdout(sprintf("- %s: %d candidate(s)\n", $job['label'], $count));
            if ($apply && $count > 0) {
                $deleted = $this->deleteInBatches(
                    $job['table'],
                    $job['condition'],
                    $job['params'],
                    $job['orderBy']
                );
                $this->stdout(sprintf("  deleted: %d\n", $deleted));
            }
        }

        if ($apply) {
            Yii::info('Maintenance cleanup completed in APPLY mode', __METHOD__);
        }

        return ExitCode::OK;
    }

    private function countRows(string $table, string $condition, array $params): int
    {
        $quotedTable = Yii::$app->db->quoteTableName($table);
        return (int)Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM {$quotedTable} WHERE {$condition}",
            $params
        )->queryScalar();
    }

    private function deleteInBatches(
        string $table,
        string $condition,
        array $params,
        string $orderBy
    ): int
    {
        $quotedTable = Yii::$app->db->quoteTableName($table);
        $deletedTotal = 0;

        for ($batch = 0; $batch < $this->maxBatches; $batch++) {
            // The extra derived table is required by MySQL when selecting from
            // the same table that is being deleted.
            $sql = "DELETE FROM {$quotedTable} WHERE id IN ("
                . "SELECT id FROM (SELECT id FROM {$quotedTable} WHERE {$condition} "
                . "ORDER BY {$orderBy} LIMIT {$this->batchSize}) purge_batch)";
            $deleted = Yii::$app->db->createCommand($sql, $params)->execute();
            $deletedTotal += $deleted;

            if ($deleted < $this->batchSize) {
                break;
            }
        }

        return $deletedTotal;
    }
}
