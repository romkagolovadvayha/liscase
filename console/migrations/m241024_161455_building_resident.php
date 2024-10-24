<?php

use console\components\migration\Migration;

/**
 * Class m241024_161455_building_resident
 */
class m241024_161455_building_resident extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('building_resident', [
            'id'         => self::PRIMARY_KEY,
            'building_id'     => self::INT_FIELD_NOT_NULL,
            'user_id'        => self::INT_FIELD_NOT_NULL,
        ], self::TABLE_OPTIONS);


        $this->addForeignKey('building_resident_building_id', 'building_resident', 'building_id',
                             'building', 'id', 'CASCADE', 'CASCADE');

        $this->addForeignKey('building_resident_user_id', 'building_resident', 'user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241024_161455_building_resident cannot be reverted.\n";

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241024_161455_building_resident cannot be reverted.\n";

        return false;
    }
    */
}
