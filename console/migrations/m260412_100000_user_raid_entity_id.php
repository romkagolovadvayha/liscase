<?php

use console\components\migration\Migration;

/**
 * Сетевой ID сущности из RaidAlerts (сопоставление с clan_plugin_cupboards.entity_id).
 */
class m260412_100000_user_raid_entity_id extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%user_raid}}');
        if ($table !== null && $table->getColumn('entity_id') === null) {
            $this->addColumn(
                '{{%user_raid}}',
                'entity_id',
                $this->string(64)->null()->comment('Network entity id из плагина (RaidAlerts)')
            );
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%user_raid}}');
        if ($table !== null && $table->getColumn('entity_id') !== null) {
            $this->dropColumn('{{%user_raid}}', 'entity_id');
        }
    }
}
