<?php

use yii\db\Migration;

/**
 * Class m260204_184334_add_icon_to_servers_tags_table
 */
class m260204_184334_add_icon_to_servers_tags_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%servers_tags}}', 'icon', $this->string(255)->null()->comment('Иконка тега'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%servers_tags}}', 'icon');
    }
}
