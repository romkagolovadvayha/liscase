<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%clan}}`.
 */
class m251001_121637_add_clan_settings_fields extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('clan', 'is_open', $this->boolean()->defaultValue(true)->comment('Открыт ли набор в клан'));
        $this->addColumn('clan', 'clan_tag', $this->string(8)->null()->comment('Тег клана (до 8 символов)'));
        $this->addColumn('clan', 'clan_color', $this->string(7)->null()->comment('Цвет клана в HEX формате'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('clan', 'is_open');
        $this->dropColumn('clan', 'clan_tag');
        $this->dropColumn('clan', 'clan_color');
    }
}