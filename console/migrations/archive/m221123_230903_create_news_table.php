 <?php

 use console\components\migration\Migration;

/**
 * Handles the creation of table `{{%news}}`.
 */
class m221123_230903_create_news_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $this->createTable('news', [
            'id'             => self::PRIMARY_KEY,
            'status'         => 'TINYINT(1) UNSIGNED NOT NULL',
            'created_at'     => self::TIMESTAMP_FIELD,
            'date_published' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('news_content', [
            'id'         => self::PRIMARY_KEY,
            'news_id'    => self::INT_FIELD_NOT_NULL,
            'language'   => 'VARCHAR(5) NOT NULL DEFAULT "ru-RU"',
            'title'      => 'TEXT NOT NULL COLLATE utf8mb4_unicode_ci',
            'title_text' => 'VARCHAR(150) NOT NULL COLLATE utf8mb4_unicode_ci',
            'body'       => 'TEXT NOT NULL COLLATE utf8mb4_unicode_ci',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS_MB4);

        $this->createIndex('index_news_language', 'news_content', 'news_id,language', true);

        $this->addForeignKey('fk_news_content_news_id', 'news_content', 'news_id',
            'news', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('news_image', [
            'id'         => self::PRIMARY_KEY,
            'news_id'    => self::INT_FIELD_NOT_NULL,
            'type'       => self::INT_FIELD_NOT_NULL,
            'image'      => 'TEXT NOT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->addForeignKey('fk_news_image_news_id', 'news_image', 'news_id',
            'news', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('box_image', [
            'id'         => self::PRIMARY_KEY,
            'box_id'     => self::INT_FIELD_NOT_NULL,
            'type'       => self::INT_FIELD_NOT_NULL,
            'image'      => 'TEXT NOT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);

        $this->createTable('drop_image', [
            'id'         => self::PRIMARY_KEY,
            'drop_id'    => self::INT_FIELD_NOT_NULL,
            'type'       => self::INT_FIELD_NOT_NULL,
            'image'      => 'TEXT NOT NULL',
            'created_at' => self::TIMESTAMP_FIELD,
        ], self::TABLE_OPTIONS);
        $this->dropColumn('box', 'image');
        $this->dropColumn('drop', 'image');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%news}}');
    }
}
