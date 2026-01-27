<?php

use console\components\migration\Migration;

/**
 * Handles adding column `update_at` to table `blog`.
 */
class m260121_130000_add_update_at_to_blog_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем поле update_at
        if (!$this->getDb()->getSchema()->getTableSchema('blog')->getColumn('update_at')) {
            $this->addColumn('blog', 'update_at', self::TIMESTAMP_FIELD . ' COMMENT \'Дата обновления\'');
            
            // Устанавливаем значение по умолчанию для существующих записей (равное created_at)
            $this->execute('UPDATE blog SET update_at = created_at WHERE update_at IS NULL');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        if ($this->getDb()->getSchema()->getTableSchema('blog')->getColumn('update_at')) {
            $this->dropColumn('blog', 'update_at');
        }
    }
}

