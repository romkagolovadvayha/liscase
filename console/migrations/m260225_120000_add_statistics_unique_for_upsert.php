<?php

use console\components\migration\Migration;

/**
 * Уникальный индекс для таблицы statistics: пакетный upsert в UpdateStatsUsersJob
 * (steam_id, server_tag, wipe, key) — одна строка на комбинацию.
 *
 * На таблицах 20M+ строк миграция может занять 10–30+ минут. Дубликаты обрабатываются
 * по чанкам по id, чтобы не превышать лимит блокировок InnoDB (error 1206).
 */
class m260225_120000_add_statistics_unique_for_upsert extends Migration
{
    private const INDEX_UNIQUE = 'idx_statistics_steam_server_wipe_key';
    private const INDEX_DEDUP = 'idx_statistics_steam_server_wipe_key_dedup';
    /** Размер чанка по id — меньше = меньше блокировок за раз, но больше проходов */
    private const CHUNK_SIZE = 100000;

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableName = $this->db->schema->getRawTableName('{{%statistics}}');

        $uniqueExists = $this->db->createCommand("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :idx
        ", [':t' => $tableName, ':idx' => self::INDEX_UNIQUE])->queryScalar();
        if ($uniqueExists) {
            return;
        }

        $dedupExists = $this->db->createCommand("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :idx
        ", [':t' => $tableName, ':idx' => self::INDEX_DEDUP])->queryScalar();

        // Нет dedup-индекса: либо первый запуск, либо дедуп уже сделан и упало только на CREATE UNIQUE (1878).
        // Пробуем сразу создать уникальный индекс; при 1062 (дубликаты ещё есть) — делаем полный дедуп.
        if (!$dedupExists) {
            try {
                $this->execute("
                    CREATE UNIQUE INDEX " . self::INDEX_UNIQUE . "
                    ON {$tableName} (steam_id, server_tag, wipe, `key`)
                ");
                return;
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), '1062') === false && strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
                // В таблице ещё дубликаты — создаём dedup-индекс и идём полным путём
            }
        }

        if (!$dedupExists) {
            $this->execute("
                CREATE INDEX " . self::INDEX_DEDUP . "
                ON {$tableName} (steam_id, server_tag, wipe, `key`)
            ");
        }

        // 1. Собираем дубликаты по чанкам (id), чтобы не превышать lock table size
        // InnoDB на диске — MEMORY упёрлась бы в лимит RAM (error 1114) на больших таблицах
        // Превфиксы в PK: лимит MySQL 3072 байт (utf8mb4), 4×191 ≈ 3056
        $this->execute("
            CREATE TEMPORARY TABLE _stat_dup_agg (
                steam_id VARCHAR(255) NOT NULL,
                server_tag VARCHAR(255) NOT NULL,
                wipe VARCHAR(255) NOT NULL,
                `key` VARCHAR(255) NOT NULL,
                keep_id INT UNSIGNED NOT NULL,
                total INT UNSIGNED NOT NULL DEFAULT 0,
                cnt INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (steam_id(191), server_tag(191), wipe(191), `key`(191))
            ) ENGINE=InnoDB
        ");
        $minMax = $this->db->createCommand("SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM {$tableName}")->queryOne();
        $minId = (int) ($minMax['min_id'] ?? 0);
        $maxId = (int) ($minMax['max_id'] ?? 0);
        for ($low = $minId; $low <= $maxId; $low += self::CHUNK_SIZE) {
            $high = min($low + self::CHUNK_SIZE - 1, $maxId);
            $this->execute("
                INSERT INTO _stat_dup_agg (steam_id, server_tag, wipe, `key`, keep_id, total, cnt)
                SELECT steam_id, server_tag, wipe, `key`, MIN(id), SUM(value), COUNT(*)
                FROM {$tableName}
                WHERE id BETWEEN {$low} AND {$high}
                GROUP BY steam_id, server_tag, wipe, `key`
                ON DUPLICATE KEY UPDATE
                    total = total + VALUES(total),
                    keep_id = LEAST(keep_id, VALUES(keep_id)),
                    cnt = cnt + VALUES(cnt)
            ");
        }
        $this->execute("DELETE FROM _stat_dup_agg WHERE cnt <= 1");
        $hasDupes = $this->db->createCommand("SELECT COUNT(*) FROM _stat_dup_agg")->queryScalar();
        if ($hasDupes > 0) {
            $this->execute("
                UPDATE {$tableName} s
                INNER JOIN _stat_dup_agg a ON s.steam_id = a.steam_id AND s.server_tag = a.server_tag
                    AND s.wipe = a.wipe AND s.`key` = a.`key` AND s.id = a.keep_id
                SET s.value = a.total
            ");
            $this->execute("
                DELETE s FROM {$tableName} s
                INNER JOIN _stat_dup_agg a ON s.steam_id = a.steam_id AND s.server_tag = a.server_tag
                    AND s.wipe = a.wipe AND s.`key` = a.`key` AND s.id != a.keep_id
            ");
        }
        $this->execute("DROP TEMPORARY TABLE IF EXISTS _stat_dup_agg");

        // 2. Убираем временный индекс и создаём уникальный
        $dedupExistsNow = $this->db->createCommand("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :idx
        ", [':t' => $tableName, ':idx' => self::INDEX_DEDUP])->queryScalar();
        if ($dedupExistsNow) {
            $this->execute("DROP INDEX " . self::INDEX_DEDUP . " ON {$tableName}");
        }
        $uniqueExists = $this->db->createCommand("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :idx
        ", [':t' => $tableName, ':idx' => self::INDEX_UNIQUE])->queryScalar();
        if (!$uniqueExists) {
            $this->execute("
                CREATE UNIQUE INDEX " . self::INDEX_UNIQUE . "
                ON {$tableName} (steam_id, server_tag, wipe, `key`)
            ");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $tableName = $this->db->schema->getRawTableName('{{%statistics}}');
        $indexName = 'idx_statistics_steam_server_wipe_key';
        try {
            $this->execute("DROP INDEX {$indexName} ON {$tableName}");
        } catch (\Exception $e) {
            // ignore
        }
    }
}
