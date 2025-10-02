<?php

use yii\db\Migration;

/**
 * Handles updating the length of clan_tag column in table `{{%clan}}`.
 */
class m251001_122649_update_clan_tag_length extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->alterColumn('clan', 'clan_tag', $this->string(8)->null()->comment('Тег клана (до 8 символов)'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->alterColumn('clan', 'clan_tag', $this->string(5)->null()->comment('Тег клана (до 5 символов)'));
    }
}