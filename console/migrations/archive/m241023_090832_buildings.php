<?php

use console\components\migration\Migration;

/**
 * Class m241023_090832_buildings
 */
class m241023_090832_buildings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('building', [
            'id'         => self::PRIMARY_KEY,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'name'    => 'VARCHAR(255) DEFAULT NULL',
            'description'    => 'VARCHAR(512) DEFAULT NULL',
            'location'    => 'VARCHAR(32) DEFAULT NULL',
            'status'          => self::TINYINT_FIELD,
            'wipe'    => 'VARCHAR(30) DEFAULT NULL',
            'likes'          => 'INT(10) UNSIGNED DEFAULT 0',
            'server_tag'    => 'VARCHAR(11) DEFAULT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('building_image', [
            'id'         => self::PRIMARY_KEY,
            'building_id'     => self::INT_FIELD_NOT_NULL,
            'image'      => 'TEXT NOT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('building_like', [
            'id'         => self::PRIMARY_KEY,
            'building_id'     => self::INT_FIELD_NOT_NULL,
            'user_id'        => self::INT_FIELD_NOT_NULL,
            'type'          => self::TINYINT_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('building_image_building_id', 'building_image', 'building_id',
                             'building', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('building_like_building_id', 'building_like', 'building_id',
                             'building', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('building_user_id', 'building', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('building_like_user_id', 'building_like', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241023_090832_buildings cannot be reverted.\n";

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241023_090832_buildings cannot be reverted.\n";

        return false;
    }
    */
}
