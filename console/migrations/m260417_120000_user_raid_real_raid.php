<?php

use console\components\migration\Migration;

/**
 * Флаг «подлинный» рейд шкафа: не накрутка своим кланом / альтом (см. SaveRaidJob).
 */
class m260417_120000_user_raid_real_raid extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%user_raid}}');
        if ($table === null || $table->getColumn('real_raid') !== null) {
            return;
        }

        $this->addColumn(
            '{{%user_raid}}',
            'real_raid',
            $this->tinyInteger(1)->unsigned()->notNull()->defaultValue(0)->comment('1 = проверенный рейд чужого шкафа (cupboard), 0 = иначе')
        );
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema('{{%user_raid}}', true);
        if ($schema === null || $schema->getColumn('real_raid') === null) {
            return;
        }
        $this->dropColumn('{{%user_raid}}', 'real_raid');
    }
}
