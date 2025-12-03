<?php

use yii\db\Migration;

/**
 * Class m251203_070950_add_user_box_indexes
 * Добавляет индексы для таблицы user_box для оптимизации запросов ежедневных наград
 */
class m251203_070950_add_user_box_indexes extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем индекс для user_id и auto (для запросов ежедневных наград)
        $this->createIndexIfNotExists('idx_user_box_user_id_auto', 'user_box', ['user_id', 'auto']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropIndexIfExists('idx_user_box_user_id_auto', 'user_box');
    }

    /**
     * Создает индекс, если он не существует
     * @param string $name Имя индекса
     * @param string $table Имя таблицы
     * @param array|string $columns Колонки для индекса
     * @param bool $unique Уникальный индекс
     */
    protected function createIndexIfNotExists($name, $table, $columns, $unique = false)
    {
        try {
            /** @var \yii\db\Command $command */
            $command = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            $indexExists = $command->queryOne();
            if (!$indexExists) {
                $this->createIndex($name, $table, $columns, $unique);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки, если индекс уже существует
        }
    }

    /**
     * Удаляет индекс, если он существует
     * @param string $name Имя индекса
     * @param string $table Имя таблицы
     */
    protected function dropIndexIfExists($name, $table)
    {
        try {
            /** @var \yii\db\Command $command */
            $command = $this->db->createCommand("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            $indexExists = $command->queryOne();
            if ($indexExists) {
                $this->dropIndex($name, $table);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
    }
}
