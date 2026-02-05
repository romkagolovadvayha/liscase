<?php

use yii\db\Migration;

/**
 * Class m260205_082222_add_no_numbering_to_servers_rules_categories
 * Добавление поля для отключения нумерации правил в категории
 */
class m260205_082222_add_no_numbering_to_servers_rules_categories extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%servers_rules_categories}}', 'no_numbering', $this->boolean()->notNull()->defaultValue(0)->comment('Отключить нумерацию правил в категории'));
        
        // Устанавливаем no_numbering = 1 для категории "Команды на сервере"
        $this->update('{{%servers_rules_categories}}', ['no_numbering' => 1], ['name' => 'Команды на сервере']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%servers_rules_categories}}', 'no_numbering');
    }
}

