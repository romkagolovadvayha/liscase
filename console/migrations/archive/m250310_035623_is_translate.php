<?php

use console\components\migration\Migration;

/**
 * Class m250310_035623_is_translate
 */
class m250310_035623_is_translate extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('site_settings','is_translate', self::TINYINT_1_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250310_035623_is_translate cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250310_035623_is_translate cannot be reverted.\n";

        return false;
    }
    */
}
