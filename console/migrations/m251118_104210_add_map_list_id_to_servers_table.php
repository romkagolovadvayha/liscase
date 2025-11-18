<?php

use yii\db\Migration;

/**
 * Class m251118_104210_add_map_list_id_to_servers_table
 */
class m251118_104210_add_map_list_id_to_servers_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        
        if ($schema && !$schema->getColumn('map_list_id')) {
            $mapListIdColumn = $this->db->schema->getTableSchema('{{%map_list}}')->getColumn('id');
            
            // Определяем тип колонки для map_list_id на основе типа id в map_list
            if ($mapListIdColumn) {
                $type = $this->resolveColumnType($mapListIdColumn);
            } else {
                $type = $this->integer();
            }
            
            $this->addColumn('{{%servers}}', 'map_list_id', $type->null()->comment('ID карты из списка'));
            
            // Добавляем индекс
            $this->createIndex('idx-servers-map_list_id', '{{%servers}}', 'map_list_id');
            
            // Добавляем внешний ключ
            $this->addForeignKey(
                'fk-servers-map_list_id',
                '{{%servers}}',
                'map_list_id',
                '{{%map_list}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%servers}}');
        if (!$schema) {
            return;
        }

        if ($schema->getColumn('map_list_id')) {
            // Удаляем внешний ключ
            $this->dropForeignKey('fk-servers-map_list_id', '{{%servers}}');
            
            // Удаляем индекс
            $this->dropIndex('idx-servers-map_list_id', '{{%servers}}');
            
            // Удаляем колонку
            $this->dropColumn('{{%servers}}', 'map_list_id');
        }
    }
    
    /**
     * Определяет тип колонки на основе существующей колонки
     * 
     * @param \yii\db\ColumnSchema $column
     * @return \yii\db\ColumnSchemaBuilder
     */
    private function resolveColumnType($column)
    {
        if ($column === null) {
            return $this->integer();
        }

        switch ($column->type) {
            case \yii\db\Schema::TYPE_BIGINT:
                $builder = $this->bigInteger();
                break;
            case \yii\db\Schema::TYPE_SMALLINT:
                $builder = $this->smallInteger();
                break;
            case \yii\db\Schema::TYPE_TINYINT:
                $builder = $this->tinyInteger();
                break;
            default:
                $builder = $this->integer();
        }

        if ($column->unsigned) {
            $builder->unsigned();
        }

        return $builder;
    }
}

