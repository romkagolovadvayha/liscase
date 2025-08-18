<?php

use console\components\migration\Migration;

/**
 * Class m250619_212405_map_list
 */
class m250619_212405_map_list extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('map_list', [
            'id'         => self::PRIMARY_KEY,
            'hash'       => self::VARCHAR_FIELD,
            'url'        => self::VARCHAR_FIELD,
            'image'      => self::VARCHAR_FIELD,
            'size'       => self::VARCHAR_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);


        $this->addColumn('map','map_list_id', self::INT_FIELD);
        $this->addForeignKey('map_map_list_id', 'map', 'map_list_id',
                             'map_list', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250619_212405_map_list cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250619_212405_map_list cannot be reverted.\n";

        return false;
    }
    */
}
