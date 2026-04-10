<?php

use console\components\migration\Migration;

/**
 * Разбивка блоков на upkeep шкафа по грейду (ClanCupboardReporter protected_blocks_by_grade).
 */
class m260413_120000_clan_plugin_cupboards_blocks_by_grade extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%clan_plugin_cupboards}}');
        if ($table === null) {
            return;
        }
        $add = function (string $col, string $comment) use ($table): void {
            if ($table->getColumn($col) === null) {
                $this->addColumn(
                    '{{%clan_plugin_cupboards}}',
                    $col,
                    $this->integer()->unsigned()->notNull()->defaultValue(0)->comment($comment)
                );
            }
        };
        $add('blocks_twigs', 'Солома (Twigs)');
        $add('blocks_wood', 'Дерево');
        $add('blocks_stone', 'Камень');
        $add('blocks_metal', 'Металл');
        $add('blocks_hqm', 'МВК (TopTier)');
    }

    public function safeDown()
    {
        foreach (['blocks_hqm', 'blocks_metal', 'blocks_stone', 'blocks_wood', 'blocks_twigs'] as $col) {
            $table = $this->db->schema->getTableSchema('{{%clan_plugin_cupboards}}', true);
            if ($table !== null && $table->getColumn($col) !== null) {
                $this->dropColumn('{{%clan_plugin_cupboards}}', $col);
            }
        }
    }
}
