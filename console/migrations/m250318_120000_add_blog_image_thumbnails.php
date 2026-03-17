<?php

use console\components\migration\Migration;

/**
 * Добавляет поля image_100 и image_400 в таблицу blog_image для уменьшенных копий.
 */
class m250318_120000_add_blog_image_thumbnails extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $table = $this->getDb()->getSchema()->getTableSchema('blog_image');
        if ($table && !$table->getColumn('image_100')) {
            $this->addColumn('blog_image', 'image_100', $this->string(255)->null()->comment('Уменьшенная копия 100px'));
        }
        if ($table && !$table->getColumn('image_400')) {
            $this->addColumn('blog_image', 'image_400', $this->string(255)->null()->comment('Уменьшенная копия 400px'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $table = $this->getDb()->getSchema()->getTableSchema('blog_image');
        if ($table && $table->getColumn('image_100')) {
            $this->dropColumn('blog_image', 'image_100');
        }
        if ($table && $table->getColumn('image_400')) {
            $this->dropColumn('blog_image', 'image_400');
        }
    }
}
