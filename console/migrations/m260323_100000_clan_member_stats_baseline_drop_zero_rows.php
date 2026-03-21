<?php

use console\components\migration\Migration as BaseMigration;

/**
 * Нулевые baseline не несут смысла: дельта считает отсутствие строки как 0.
 * После удаления пустых групп следующий пересчёт добавит маркер {@see ClanMemberStatsBaseline::MARKER_STAT_KEY} при необходимости.
 */
class m260323_100000_clan_member_stats_baseline_drop_zero_rows extends BaseMigration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('clan_member_stats_baseline', true);
        if ($schema === null) {
            return;
        }

        $this->delete('clan_member_stats_baseline', ['value' => 0]);
    }

    public function safeDown()
    {
        // Восстановить нули из бэкапа при необходимости; обратная операция не детерминирована.
    }
}
