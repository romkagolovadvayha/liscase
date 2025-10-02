<?php

use yii\db\Migration;

/**
 * Class m251001_022357_add_link_name_to_clan_page
 */
class m251001_022357_add_link_name_to_clan_page extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('clan_page', 'link_name', $this->string(255)->null()->after('sort'));
        $this->createIndex('idx-clan_page-link_name', 'clan_page', 'link_name');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndex('idx-clan_page-link_name', 'clan_page');
        $this->dropColumn('clan_page', 'link_name');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m251001_022357_add_link_name_to_clan_page cannot be reverted.\n";

        return false;
    }
    */
}
