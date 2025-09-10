<?php

use yii\db\Migration;

/**
 * Class m250906_121937_template
 */
class m250906_121937_template extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%template_file}}', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->notNull(),
            'path' => $this->string(512)->notNull(),           // относительный путь от корня алиаса (common/frontend)
            'root_alias' => $this->string(32)->notNull(),      // 'frontend' или 'common'
            'ext' => $this->string(16)->notNull(),             // php|twig|scss
            'content' => $this->string()->notNull(),
            'checksum' => $this->string(64)->null(),           // sha256 содержимого
            'updated_by' => $this->integer()->null(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addForeignKey(
            'fk_template_file_template',
            '{{%template_file}}',
            'template_id',
            '{{%template}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('ux_template_file_unique', '{{%template_file}}', ['template_id', 'root_alias', 'path'], true);
        $this->createIndex('idx_template_file_ext', '{{%template_file}}', ['ext']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250906_121937_template cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250906_121937_template cannot be reverted.\n";

        return false;
    }
    */
}
