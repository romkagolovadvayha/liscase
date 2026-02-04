<?php

use yii\db\Migration;

/**
 * Class m260204_105422_remove_max_from_servers_statistics_history
 * Удаляет колонку max из таблицы servers_statistics_history
 */
class m260204_105422_remove_max_from_servers_statistics_history extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->dropColumn('{{%servers_statistics_history}}', 'max');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->addColumn('{{%servers_statistics_history}}', 'max', $this->integer()->notNull()->defaultValue(0)->comment('Максимальный онлайн'));
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260204_105422_remove_max_from_servers_statistics_history cannot be reverted.\n";

        return false;
    }
    */
}
