<?php

use console\components\migration\Migration as BaseMigration;

/**
 * Нулевые значения в clan_statistics_values не нужны: отсутствие строки трактуется как 0 в коде.
 */
class m260323_110000_clan_statistics_values_drop_zero_rows extends BaseMigration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('clan_statistics_values', true);
        if ($schema === null) {
            return;
        }

        $this->delete('clan_statistics_values', ['value' => 0]);
    }

    public function safeDown()
    {
        // Восстановление нулей из бэкапа при необходимости.
    }
}
