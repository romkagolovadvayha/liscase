<?php

use yii\db\Migration;

/**
 * Class m241026_105500_support_add_column
 */
class m241026_105500_support_add_column extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('support','suspect_user_id', 'INT(10) UNSIGNED DEFAULT NULL AFTER user_id');
        $this->addColumn('support','server_tag', 'VARCHAR(11) DEFAULT NULL AFTER suspect_user_id');

        $this->addForeignKey('support_suspect_user_id', 'support', 'suspect_user_id',
                             'user', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241026_105500_support_add_column cannot be reverted.\n";

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241026_105500_support_add_column cannot be reverted.\n";

        return false;
    }
    */
}
