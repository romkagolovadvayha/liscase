<?php

use console\components\migration\Migration;
/**
 * Class m250901_135903_staging
 */
class m250901_135903_staging extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('map','is_staging', self::TINYINT_1_FIELD);
        $this->addColumn('map','name', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250901_135903_staging cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250901_135903_staging cannot be reverted.\n";

        return false;
    }
    */
}
