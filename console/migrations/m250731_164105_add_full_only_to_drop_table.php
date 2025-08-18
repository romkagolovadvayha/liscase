<?php

use yii\db\Migration;

/**
 * Class m250731_164105_add_full_only_to_drop_table
 */
class m250731_164105_add_full_only_to_drop_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('drop', 'full_only', $this->boolean()->notNull()->defaultValue(1)
                                                   ->comment('1 - выводить только целиком, 0 - можно частично'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250731_164105_add_full_only_to_drop_table cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250731_164105_add_full_only_to_drop_table cannot be reverted.\n";

        return false;
    }
    */
}
