<?php

use yii\db\Migration;

/**
 * Class m241027_115123_support_file_
 */
class m241027_115123_support_file_ extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('support_file','filename', 'VARCHAR(255) DEFAULT NULL AFTER file');
        $this->addColumn('support_file','mimetype', 'VARCHAR(255) DEFAULT NULL AFTER filename');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241027_115123_support_file_ cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241027_115123_support_file_ cannot be reverted.\n";

        return false;
    }
    */
}
