<?php

use console\components\migration\Migration;
use yii\db\Exception as DbException;
use Yii;

/**
 * Покрывающие индексы для stats/active-players-cache (шаг 1: SUM(playtime) по steam_id).
 *
 * На большой statistics CREATE INDEX может идти долго — перед DDL увеличиваем SESSION timeouts
 * и используем ALGORITHM=INPLACE LOCK=NONE; при 2006/2002 — reconnect и повтор.
 *
 * @see \console\controllers\StatsController::actionActivePlayersCache
 */
class m260702_120000_statistics_covering_index_playtime_agg extends Migration
{
    public function safeUp()
    {
        $this->createStatisticsIndexIfNotExists(
            'idx_statistics_key_steam_value',
            '(`key`, steam_id, value)'
        );

        $this->createStatisticsIndexIfNotExists(
            'idx_statistics_key_server_steam_value',
            '(`key`, server_tag, steam_id, value)'
        );
    }

    public function safeDown()
    {
        $this->dropIndexIfExists('idx_statistics_key_steam_value', 'statistics');
        $this->dropIndexIfExists('idx_statistics_key_server_steam_value', 'statistics');
    }

    private function createStatisticsIndexIfNotExists(string $name, string $columnsSql): void
    {
        if ($this->indexExists($name)) {
            echo "Index {$name} already exists, skip.\n";

            return;
        }

        $this->ensureLongRunningDdlSession();

        $attempt = 0;
        $maxAttempts = 3;
        while ($attempt < $maxAttempts) {
            try {
                $this->execute(
                    "CREATE INDEX {$name} ON statistics {$columnsSql} ALGORITHM=INPLACE LOCK=NONE"
                );

                return;
            } catch (\Throwable $e) {
                if ($this->isDdlAlgorithmUnsupported($e)) {
                    echo "Online DDL hint not supported, fallback CREATE INDEX {$name}…\n";
                    $this->ensureLongRunningDdlSession();
                    $this->execute("CREATE INDEX {$name} ON statistics {$columnsSql}");

                    return;
                }

                $attempt++;
                if ($attempt >= $maxAttempts || !$this->isConnectionLost($e)) {
                    throw $e;
                }

                echo "MySQL DDL reconnect ({$name}, attempt {$attempt}): {$e->getMessage()}\n";
                sleep(min($attempt, 5));
                $this->reconnectDb();
                $this->ensureLongRunningDdlSession();
            }
        }
    }

    private function indexExists(string $name): bool
    {
        return (bool) $this->db->createCommand("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
            AND table_name = 'statistics'
            AND index_name = :name
        ", [':name' => $name])->queryScalar();
    }

    private function ensureLongRunningDdlSession(): void
    {
        foreach ([
            'SET SESSION wait_timeout = 28800',
            'SET SESSION interactive_timeout = 28800',
            'SET SESSION net_read_timeout = 28800',
            'SET SESSION net_write_timeout = 28800',
            'SET SESSION innodb_lock_wait_timeout = 3600',
        ] as $sql) {
            $this->db->createCommand($sql)->execute();
        }
    }

    private function reconnectDb(): void
    {
        try {
            Yii::$app->db->close();
        } catch (\Throwable $e) {
            // ignore
        }
        Yii::$app->db->open();
    }

    private function isConnectionLost(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (
            stripos($msg, 'gone away') !== false
            || stripos($msg, 'lost connection') !== false
            || stripos($msg, 'no such file or directory') !== false
            || strpos($msg, '2006') !== false
            || strpos($msg, '2002') !== false
            || strpos($msg, '2013') !== false
        ) {
            return true;
        }
        if ($e instanceof DbException && isset($e->errorInfo[1])) {
            return in_array((int) $e->errorInfo[1], [2002, 2006, 2013], true);
        }
        $prev = $e->getPrevious();
        if ($prev instanceof \Throwable) {
            return $this->isConnectionLost($prev);
        }

        return false;
    }

    private function isDdlAlgorithmUnsupported(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return strpos($msg, 'algorithm') !== false
            || strpos($msg, 'lock=none') !== false
            || strpos($msg, 'lock none') !== false;
    }

    protected function dropIndexIfExists($name, $table)
    {
        try {
            $indexExists = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'")->queryOne();
            if ($indexExists) {
                $this->dropIndex($name, $table);
            }
        } catch (\Exception $e) {
            // ignore
        }
    }
}
