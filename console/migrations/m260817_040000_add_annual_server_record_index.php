<?php

use common\services\medals\AnnualServerRecordMedalAwarder;
use console\components\migration\Migration;

/**
 * Adds a covering index for the one-pass year/server/category aggregation.
 */
class m260817_040000_add_annual_server_record_index extends Migration
{
    public function up()
    {
        if ($this->indexExists('statistics', AnnualServerRecordMedalAwarder::STATISTICS_INDEX)) {
            return;
        }

        $this->execute(
            'ALTER TABLE `statistics` ADD INDEX `'
            . AnnualServerRecordMedalAwarder::STATISTICS_INDEX
            . '` (`key`, `wipe`, `server_tag`, `steam_id`, `value`), '
            . 'ALGORITHM=INPLACE, LOCK=NONE'
        );
    }

    public function down()
    {
        if ($this->indexExists('statistics', AnnualServerRecordMedalAwarder::STATISTICS_INDEX)) {
            $this->dropIndex(AnnualServerRecordMedalAwarder::STATISTICS_INDEX, 'statistics');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool)$this->db->createCommand(
            'SELECT 1 FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1',
            [':table' => $table, ':index' => $index]
        )->queryScalar();
    }
}
