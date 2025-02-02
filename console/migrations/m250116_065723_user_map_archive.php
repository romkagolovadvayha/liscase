<?php
use console\components\migration\Migration;

/**
 * Class m250116_065723_user_map_archive
 */
class m250116_065723_user_map_archive extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('map', 'is_archive', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250116_065723_user_map_archive cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250116_065723_user_map_archive cannot be reverted.\n";

        return false;
    }
    */
}
