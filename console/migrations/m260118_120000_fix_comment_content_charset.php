<?php

use console\components\migration\Migration;

/**
 * Исправляет кодировку колонки content в таблице comment для поддержки эмодзи
 */
class m260118_120000_fix_comment_content_charset extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        try {
            // Изменяем кодировку колонки content на utf8mb4 для поддержки эмодзи
            $this->execute("ALTER TABLE `comment` MODIFY `content` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Содержимое комментария'");
            
            echo "✓ Кодировка колонки content изменена на utf8mb4\n";
        } catch (\Exception $e) {
            // Игнорируем ошибку, если колонка уже имеет правильную кодировку
            echo "⚠ Ошибка при изменении кодировки (возможно, уже установлена): " . $e->getMessage() . "\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Возвращаем обратно на utf8 (если нужно откатить)
        try {
            $this->execute("ALTER TABLE `comment` MODIFY `content` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'Содержимое комментария'");
            echo "✓ Кодировка колонки content возвращена на utf8\n";
        } catch (\Exception $e) {
            echo "⚠ Ошибка при откате кодировки: " . $e->getMessage() . "\n";
        }
    }
}







