<?php

use console\components\migration\Migration;

/**
 * Class m250122_205213_settings
 */
class m250122_205213_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%site_settings}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),  // название настройки
            'category' => $this->string()->notNull(),  // категория настройки
            'type' => $this->string()->notNull(),  // тип настройки
            'value' => $this->text()->notNull(),  // значение настройки
            'code' => $this->string()->null(),  // поле для связи с кодом сайта
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        // Добавим вручную тип ENUM для поля 'type' через SQL
        $this->execute("ALTER TABLE {{%site_settings}} CHANGE COLUMN `type` `type` ENUM('text', 'color', 'file', 'image', 'number', 'checkbox', 'radio') NOT NULL");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%site_settings}}');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250122_205213_settings cannot be reverted.\n";

        return false;
    }
    */
}
