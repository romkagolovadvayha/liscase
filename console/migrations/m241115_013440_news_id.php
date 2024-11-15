<?php

use console\components\migration\Migration;

/**
 * Class m241115_013440_news_id
 */
class m241115_013440_news_id extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('blog','news_id', self::VARCHAR_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m241115_013440_news_id cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m241115_013440_news_id cannot be reverted.\n";

        return false;
    }
    */
}
