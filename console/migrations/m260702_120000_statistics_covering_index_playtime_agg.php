<?php

use console\components\migration\Migration;
use yii\db\Exception as DbException;

/**
 * Покрывающие индексы для stats/active-players-cache (шаг 1: SUM(playtime) по steam_id).
 *
 * На большой statistics CREATE INDEX может идти долго — перед DDL увеличиваем SESSION timeouts;
 * при 2006/2002 — reconnect и повтор. Ошибка 2006 в тексте Yii содержит исходный SQL с
 * ALGORITHM=INPLACE — не путать с «unsupported algorithm».
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

        $sqlVariants = [
            "CREATE INDEX {$name} ON statistics {$columnsSql} ALGORITHM=INPLACE LOCK=NONE",
            "CREATE INDEX {$name} ON statistics {$columnsSql}",
        ];
        $variantIndex = 0;
        $attempt = 0;
        $maxAttempts = 5;

        while ($attempt < $maxAttempts) {
            if ($this->indexExists($name)) {
                echo "Index {$name} already exists after reconnect, skip.\n";

                return;
            }

            $this->ensureLongRunningDdlSession();

            try {
                $this->execute($sqlVariants[$variantIndex]);

                return;
            } catch (\Throwable $e) {
                if ($this->indexExists($name)) {
                    echo "Index {$name} created despite error, skip.\n";

                    return;
                }

                if ($this->isConnectionLost($e)) {
                    $attempt++;
                    echo "MySQL DDL reconnect ({$name}, attempt {$attempt}): {$e->getMessage()}\n";
                    sleep(min($attempt, 10));
                    $this->reconnectDb();
                    continue;
                }

                if ($variantIndex === 0 && $this->isDdlAlgorithmUnsupported($e)) {
                    echo "Online DDL hint not supported, fallback CREATE INDEX {$name}…\n";
                    $variantIndex = 1;
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException("Failed to create index {$name} after {$maxAttempts} attempts");
    }

    private function indexExists(string $name): bool
    {
        try {
            return (bool) $this->db->createCommand("
                SELECT COUNT(*)
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = 'statistics'
                AND index_name = :name
            ", [':name' => $name])->queryScalar();
        } catch (\Throwable $e) {
            if (!$this->isConnectionLost($e)) {
                throw $e;
            }
            $this->reconnectDb();

            return (bool) $this->db->createCommand("
                SELECT COUNT(*)
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = 'statistics'
                AND index_name = :name
            ", [':name' => $name])->queryScalar();
        }
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
            $this->executeSessionSql($sql);
        }
    }

    private function executeSessionSql(string $sql): void
    {
        try {
            $this->db->createCommand($sql)->execute();
        } catch (\Throwable $e) {
            if (!$this->isConnectionLost($e)) {
                throw $e;
            }
            $this->reconnectDb();
            $this->db->createCommand($sql)->execute();
        }
    }

    private function reconnectDb(): void
    {
        try {
            $this->db->close();
        } catch (\Throwable $e) {
            // ignore
        }
        $this->db->open();
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
        if ($this->isConnectionLost($e)) {
            return false;
        }

        $msg = strtolower($e->getMessage());
        $serverMsg = $msg;
        if (($pos = strpos($msg, 'the sql being executed was:')) !== false) {
            $serverMsg = substr($msg, 0, $pos);
        }

        return strpos($serverMsg, 'algorithm') !== false
            || strpos($serverMsg, 'lock=none') !== false
            || strpos($serverMsg, 'lock none') !== false;
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
