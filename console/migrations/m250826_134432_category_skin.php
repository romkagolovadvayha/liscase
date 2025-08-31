<?php

use console\components\migration\Migration;

/**
 * Class m250826_134432_category_skin
 */
class m250826_134432_category_skin extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('server_skin_category', [
            'id'         => self::PRIMARY_KEY,
            'name'       => self::VARCHAR_FIELD,
            'key'        =>  self::VARCHAR_FIELD,
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addColumn('server_skin','server_skin_category_id', self::INT_FIELD);
        $this->addForeignKey('server_skin_server_skin_category_id', 'server_skin', 'server_skin_category_id',
                             'server_skin_category', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250826_134432_category_skin cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250826_134432_category_skin cannot be reverted.\n";

        return false;
    }
    */
}
