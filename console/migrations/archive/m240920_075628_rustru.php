<?php

use console\components\migration\Migration;

/**
 * Class m240920_075628_rustru
 */
class m240920_075628_rustru extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('user','rustru_activated', self::TINYINT_1_FIELD);
        $this->addColumn('user','rustru_scrap_confirm', 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0');
        $this->addColumn('user','rustru_scrap_wait', 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {

    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m240920_075628_rustru cannot be reverted.\n";

        return false;
    }
    */
}
