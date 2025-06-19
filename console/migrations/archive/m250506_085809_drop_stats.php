<?php

use console\components\migration\Migration;

/**
 * Class m250506_085809_drop_stats
 */
class m250506_085809_drop_stats extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('drop_stat', [
            'id'         => self::PRIMARY_KEY,
            'drop_id'        => self::INT_FIELD_NOT_NULL,
            'stat_key'    => self::VARCHAR_FIELD,
            'blocked_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);


        $this->addForeignKey('drop_stat_drop_id', 'drop_stat', 'drop_id',
                             'drop', 'id', 'CASCADE', 'CASCADE');

        $this->addColumn('drop_stat','value', self::INT_FIELD);
        $this->renameColumn('drop_stat','blocked_at', 'created_at');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250506_085809_drop_stats cannot be reverted.\n";

        //return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250506_085809_drop_stats cannot be reverted.\n";

        return false;
    }
    */
}
