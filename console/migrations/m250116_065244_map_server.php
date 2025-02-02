<?php

use console\components\migration\Migration;

/**
 * Class m250116_065244_map_server
 */
class m250116_065244_map_server extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('map', 'server_id', 'INT(11) DEFAULT NULL');
        // Добавляем внешние ключи
        $this->addForeignKey(
            'fk_map_server_id',
            'map',
            'server_id',
            'servers', // Таблица пользователей
            'id',
            'CASCADE'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250116_065244_map_server cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250116_065244_map_server cannot be reverted.\n";

        return false;
    }
    */
}
