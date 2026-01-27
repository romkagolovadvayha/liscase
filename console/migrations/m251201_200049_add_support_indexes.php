<?php

use console\components\migration\Migration;

/**
 * Class m251201_200049_add_support_indexes
 * 
 * Добавляет индексы для оптимизации запросов к таблицам support
 */
class m251201_200049_add_support_indexes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Индексы для таблицы support
        // Индекс для сортировки по статусу и дате обновления (используется в ORDER BY status, updated_at DESC)
        $this->createIndexIfNotExists('idx_support_status_updated_at', 'support', ['status', 'updated_at']);
        
        // Индекс для сортировки по дате обновления (дополнительный для случаев без фильтрации по статусу)
        $this->createIndexIfNotExists('idx_support_updated_at', 'support', 'updated_at');
        
        // Индекс для таблицы support_message
        // Индекс для сортировки по дате создания (используется в ORDER BY created_at ASC)
        $this->createIndexIfNotExists('idx_support_message_created_at', 'support_message', 'created_at');
        
        // Индекс для таблицы support_read
        // Композитный индекс для фильтрации по пользователю и статусу (используется в WHERE user_id = X AND status = 0)
        $this->createIndexIfNotExists('idx_support_read_user_status', 'support_read', ['user_id', 'status']);
        
        // Индекс для группировки по support_id (используется в GROUP BY support_id)
        $this->createIndexIfNotExists('idx_support_read_support_id', 'support_read', 'support_id');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем индексы в обратном порядке
        $this->dropIndex('idx_support_read_support_id', 'support_read');
        $this->dropIndex('idx_support_read_user_status', 'support_read');
        $this->dropIndex('idx_support_message_created_at', 'support_message');
        $this->dropIndex('idx_support_updated_at', 'support');
        $this->dropIndex('idx_support_status_updated_at', 'support');
    }

    /**
     * Создает индекс, если он не существует
     * 
     * @param string $name Имя индекса
     * @param string $table Имя таблицы
     * @param string|string[] $columns Колонки для индекса
     * @param bool $unique Уникальный ли индекс
     */
    protected function createIndexIfNotExists($name, $table, $columns, $unique = false)
    {
        try {
            $indexExists = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'")->queryOne();
            if (!$indexExists) {
                $this->createIndex($name, $table, $columns, $unique);
                echo "  > Created index '{$name}' on table '{$table}'\n";
            } else {
                echo "  > Index '{$name}' already exists on table '{$table}'\n";
            }
        } catch (\Exception $e) {
            echo "  > Error creating index '{$name}': " . $e->getMessage() . "\n";
        }
    }
}
