<?php

use console\components\migration\Migration;

/**
 * Class m250115_092300_maps
 */
class m250115_092300_maps extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('map', [
            'id'           => self::PRIMARY_KEY,
            'mapId'         => 'VARCHAR(255) DEFAULT NULL',
            'link'         => 'VARCHAR(255) DEFAULT NULL',
            'seed'         => self::INT_FIELD_NOT_NULL,
            'size'         => self::INT_FIELD_NOT_NULL,
            'version'         => self::INT_FIELD_NOT_NULL,
            'image_link'   => 'VARCHAR(255) DEFAULT NULL',
            'image_link_icons' => 'VARCHAR(255) DEFAULT NULL',
            'created_at'   => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addColumn('servers', 'map_id', self::INT_FIELD);

        $this->addForeignKey('servers_map_id', 'servers', 'map_id',
                             'map', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250115_092300_maps cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250115_092300_maps cannot be reverted.\n";

        return false;
    }
    */
}
