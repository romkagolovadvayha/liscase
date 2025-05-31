<?php

use yii\db\Migration;

/**
 * Class m250420_114745_comission
 */
class m250420_114745_comission extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('deposit','commission', 'DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250420_114745_comission cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250420_114745_comission cannot be reverted.\n";

        return false;
    }
    */
}
