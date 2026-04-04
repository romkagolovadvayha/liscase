<?php

use yii\db\Migration;

/**
 * Цвет тега клана (для чата в игре / плагин ClanManager).
 */
class m260404_120000_clans_color_tag extends Migration
{
    private const DEFAULT = '#5DCEA4';

    public function safeUp()
    {
        $this->addColumn('clans', 'color_tag', $this->string(20)->notNull()->defaultValue(self::DEFAULT));
    }

    public function safeDown()
    {
        $this->dropColumn('clans', 'color_tag');
    }
}
