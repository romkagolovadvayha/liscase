<?php

use yii\db\Migration;

/**
 * Class m241221_183847_not_donat_server
 */
class m241221_183847_not_donat_server extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('servers','is_store', 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 1');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241221_183847_not_donat_server cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241221_183847_not_donat_server cannot be reverted.\n";

        return false;
    }
    */
}
