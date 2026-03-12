<?php

use console\components\migration\Migration;

/**
 * Handles the creation of table `comment_like`.
 */
class m260118_000000_create_comment_like_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableName = 'comment_like';
        $tableExists = $this->db->schema->getTableSchema($tableName, true) !== null;

        if (!$tableExists) {
            // Таблица лайков комментариев
            // Используем INT(11) для comment_id, так как таблица comment, вероятно, использует INT(11) для id
            $this->createTable($tableName, [
                'id' => self::PRIMARY_KEY,
                'comment_id' => 'INT(11) NOT NULL COMMENT \'ID комментария\'',
                'user_id' => self::INT_FIELD_NOT_NULL . ' COMMENT \'ID пользователя\'',
                'created_at' => 'INT(10) UNSIGNED NOT NULL',
            ], self::TABLE_OPTIONS);

            $this->createIndex('idx-comment_like-comment_id', $tableName, 'comment_id');
            $this->createIndex('idx-comment_like-user_id', $tableName, 'user_id');
            $this->createIndex('idx-comment_like-created_at', $tableName, 'created_at');
            
            // Уникальный индекс для предотвращения дублирования лайков
            $this->createIndex('idx-comment_like-unique', $tableName, ['comment_id', 'user_id'], true);
        }

        // Добавляем foreign keys (проверяем существование через try-catch)
        // Foreign key для comment_id
        try {
            $this->addForeignKey(
                'fk-comment_like-comment_id',
                $tableName,
                'comment_id',
                'comment',
                'id',
                'CASCADE'
            );
        } catch (\Exception $e) {
            // Проверяем, не существует ли уже этот foreign key
            if (strpos($e->getMessage(), 'Duplicate key name') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                echo "Warning: Could not add foreign key fk-comment_like-comment_id: " . $e->getMessage() . "\n";
                // Возможно, нужно изменить тип comment_id - проверяем тип id в таблице comment
                $commentSchema = $this->db->schema->getTableSchema('comment', true);
                if ($commentSchema) {
                    $idColumn = $commentSchema->getColumn('id');
                    if ($idColumn) {
                        echo "Comment table id type: " . $idColumn->dbType . "\n";
                        echo "Try manually altering comment_like.comment_id to match comment.id type\n";
                    }
                }
            }
        }

        // Foreign key для user_id
        try {
            $this->addForeignKey(
                'fk-comment_like-user_id',
                $tableName,
                'user_id',
                'user',
                'id',
                'CASCADE'
            );
        } catch (\Exception $e) {
            // Проверяем, не существует ли уже этот foreign key
            if (strpos($e->getMessage(), 'Duplicate key name') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                echo "Warning: Could not add foreign key fk-comment_like-user_id: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk-comment_like-user_id', 'comment_like');
        $this->dropForeignKey('fk-comment_like-comment_id', 'comment_like');
        $this->dropTable('comment_like');
    }
}

