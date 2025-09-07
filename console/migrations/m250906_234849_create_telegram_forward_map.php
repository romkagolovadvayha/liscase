<?php

use yii\db\Migration;

/**
 * Class m250906_234849_create_telegram_forward_map
 */
class m250906_234849_create_telegram_forward_map extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // LONGTEXT для MySQL, иначе fallback на TEXT
        $longText = $this->db->driverName === 'mysql'
            ? $this->getDb()->getSchema()->createColumnSchemaBuilder('LONGTEXT')
            : $this->text();

        $this->createTable('{{%telegram_news}}', [
            'id'                    => $this->primaryKey(),
            'source_chat_id'        => $this->string(64)->notNull(),
            'source_message_id'     => $this->integer()->notNull(),
            'media_group_id'        => $this->string(64)->null(),
            'content_type'          => $this->string(64)->null(),

            'text'                  => $this->text()->null(),
            'caption'               => $this->text()->null(),
            'processed_text'        => $this->text()->null(),
            'processed_caption'     => $this->text()->null(),

            'target_chat_id'        => $this->string(64)->null(),
            'status'                => $this->smallInteger()->notNull()->defaultValue(0),
            'published_message_id'  => $this->integer()->null(),
            'error'                 => $this->text()->null(),

            'raw_json'              => $longText->notNull(),

            'created_at'            => $this->integer()->notNull(),
            'updated_at'            => $this->integer()->notNull(),
        ]);

        $this->createIndex(
            'uq_tg_news_src',
            '{{%telegram_news}}',
            ['source_chat_id', 'source_message_id'],
            true
        );

        $this->createIndex(
            'idx_tg_news_status',
            '{{%telegram_news}}',
            'status'
        );

        $this->createIndex(
            'idx_tg_news_media_group',
            '{{%telegram_news}}',
            'media_group_id'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250906_234849_create_telegram_forward_map cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250906_234849_create_telegram_forward_map cannot be reverted.\n";

        return false;
    }
    */
}
