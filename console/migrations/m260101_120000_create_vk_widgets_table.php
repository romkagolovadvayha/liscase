<?php

use yii\db\Migration;

/**
 * Class m260101_120000_create_vk_widgets_table
 */
class m260101_120000_create_vk_widgets_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%vk_widgets}}', [
            'id' => $this->primaryKey(),
            'group_id' => $this->integer()->notNull()->comment('ID сообщества ВК'),
            'app_id' => $this->integer()->notNull()->comment('ID приложения ВК'),
            'logo_icon_id' => $this->string(255)->null()->comment('ID иконки логотипа'),
            'api_url' => $this->string(500)->null()->comment('URL API для получения данных о серверах'),
            'access_token' => $this->text()->null()->comment('Токен доступа для обновления виджета (зашифрован)'),
            'status' => $this->smallInteger()->notNull()->defaultValue(1)->comment('Статус: 0 - отключен, 1 - активен'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->comment('Дата создания'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')->comment('Дата обновления'),
        ]);

        $this->createIndex('idx_vk_widgets_group_id', '{{%vk_widgets}}', 'group_id');
        $this->createIndex('idx_vk_widgets_status', '{{%vk_widgets}}', 'status');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%vk_widgets}}');
    }
}

