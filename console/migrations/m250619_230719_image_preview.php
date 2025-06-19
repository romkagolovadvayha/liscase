<?php

use console\components\migration\Migration;

/**
 * Class m250619_230719_image_preview
 */
class m250619_230719_image_preview extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('map_list','image_preview', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250619_230719_image_preview cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250619_230719_image_preview cannot be reverted.\n";

        return false;
    }
    */
}
