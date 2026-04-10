<?php

use console\components\migration\Migration;

/**
 * Снимок шкафа из clan_plugin_cupboards при рейде шкафа + clan_id жертв из клана.
 */
class m260415_100000_user_raid_clan_cupboard_snapshot extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%user_raid}}');
        if ($table === null) {
            return;
        }

        if ($table->getColumn('clan_id') === null) {
            $this->addColumn(
                '{{%user_raid}}',
                'clan_id',
                $this->integer()->unsigned()->null()->comment('Клан жертв (из plugin cupboards или участника из owners)')
            );
            $this->addForeignKey(
                'fk_user_raid_clan_id',
                '{{%user_raid}}',
                'clan_id',
                '{{%clans}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        $addInt = function (string $col, string $comment) use ($table): void {
            $fresh = $this->db->schema->getTableSchema('{{%user_raid}}', true);
            if ($fresh !== null && $fresh->getColumn($col) === null) {
                $this->addColumn(
                    '{{%user_raid}}',
                    $col,
                    $this->integer()->unsigned()->notNull()->defaultValue(0)->comment($comment)
                );
            }
        };

        $addInt('blocks_wood', 'Дерево (снимок с шкафа по entity_id)');
        $addInt('blocks_stone', 'Камень');
        $addInt('blocks_metal', 'Железо');
        $addInt('blocks_hqm', 'МВК');
        $addInt('score', 'Очки базы (как clan_plugin_cupboards.score)');

        $t2 = $this->db->schema->getTableSchema('{{%user_raid}}', true);
        if ($t2 !== null && $t2->getColumn('main_cupboard') === null) {
            $this->addColumn(
                '{{%user_raid}}',
                'main_cupboard',
                $this->tinyInteger(1)->unsigned()->notNull()->defaultValue(0)->comment('Главный шкаф клана (снимок)')
            );
        }
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema('{{%user_raid}}', true);
        if ($schema === null) {
            return;
        }
        try {
            $this->dropForeignKey('fk_user_raid_clan_id', '{{%user_raid}}');
        } catch (\Throwable $e) {
            // FK мог не создаться при частичном up
        }
        foreach (['main_cupboard', 'score', 'blocks_hqm', 'blocks_metal', 'blocks_stone', 'blocks_wood', 'clan_id'] as $col) {
            $s = $this->db->getTableSchema('{{%user_raid}}', true);
            if ($s !== null && $s->getColumn($col) !== null) {
                $this->dropColumn('{{%user_raid}}', $col);
            }
        }
    }
}
