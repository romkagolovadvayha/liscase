<?php

use console\components\migration\Migration;

/**
 * Клан атакующего (user_id рейда) на сервере рейда — денормализация для выборок.
 */
class m260416_110000_user_raid_raider_clan_id extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%user_raid}}');
        if ($table === null || $table->getColumn('raider_clan_id') !== null) {
            return;
        }

        $this->addColumn(
            '{{%user_raid}}',
            'raider_clan_id',
            $this->integer()->unsigned()->null()->comment('Клан игрока, совершившего рейд (активное членство на server_id)')
        );
        $this->addForeignKey(
            'fk_user_raid_raider_clan_id',
            '{{%user_raid}}',
            'raider_clan_id',
            '{{%clans}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema('{{%user_raid}}', true);
        if ($schema === null || $schema->getColumn('raider_clan_id') === null) {
            return;
        }
        try {
            $this->dropForeignKey('fk_user_raid_raider_clan_id', '{{%user_raid}}');
        } catch (\Throwable $e) {
        }
        $this->dropColumn('{{%user_raid}}', 'raider_clan_id');
    }
}
