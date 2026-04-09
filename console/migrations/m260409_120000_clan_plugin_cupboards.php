<?php

use console\components\migration\Migration;
use yii\db\ColumnSchema;
use yii\db\Schema;

/**
 * Шкафы с игровых серверов (ingest ClanCupboardReporter): квадрат карты, вайп, опционально clan_id.
 * Типы server_id / clan_id совпадают с servers.id и clans.id (MySQL 8 иначе даёт 3780 incompatible).
 */
class m260409_120000_clan_plugin_cupboards extends Migration
{
    public function safeUp()
    {
        $serversId = $this->db->schema->getTableSchema('{{%servers}}')->getColumn('id');
        $clansId = $this->db->schema->getTableSchema('{{%clans}}')->getColumn('id');

        $this->createTable('{{%clan_plugin_cupboards}}', [
            'id' => self::PRIMARY_KEY,
            'server_id' => $this->resolveColumnType($serversId)->notNull()->comment('Сервер'),
            'wipe' => 'VARCHAR(64) NOT NULL COMMENT \'Ключ вайпа (как Statistics::wipe / currentWipe)\'',
            'entity_id' => 'VARCHAR(32) NOT NULL COMMENT \'Сетевой ID шкафа на сервере\'',
            'map_square' => 'VARCHAR(16) NOT NULL COMMENT \'Квадрат карты Rust (как SignStatistics / RustApp)\'',
            'placer_steam_id' => 'VARCHAR(24) NOT NULL COMMENT \'Steam ID поставившего\'',
            'protected_blocks' => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'main_cupboard' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT \'Главный шкаф клана: max protected_blocks на сервере+вайп в группе клана\'',
            'clan_id' => $this->resolveColumnType($clansId)->null()->defaultValue(null)->comment('Клан сайта; NULL если нет в БД или unassigned'),
            'clan_tag' => 'VARCHAR(50) DEFAULT NULL COMMENT \'Тег из плагина (денормализация)\'',
            'created_at' => 'INT(10) UNSIGNED NOT NULL',
            'updated_at' => 'INT(10) UNSIGNED NOT NULL',
        ], self::TABLE_OPTIONS_MB4);

        // Уникальность: entity_id + server_id + wipe (порядок колонок в индексе — как в требовании)
        $this->createIndex(
            'uq_clan_plugin_cupboards_entity_server_wipe',
            '{{%clan_plugin_cupboards}}',
            ['entity_id', 'server_id', 'wipe'],
            true
        );
        $this->createIndex('idx_clan_plugin_cupboards_server_wipe', '{{%clan_plugin_cupboards}}', ['server_id', 'wipe']);
        $this->createIndex('idx_clan_plugin_cupboards_clan_id', '{{%clan_plugin_cupboards}}', 'clan_id');

        $this->addForeignKey(
            'fk_clan_plugin_cupboards_server_id',
            '{{%clan_plugin_cupboards}}',
            'server_id',
            '{{%servers}}',
            'id',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_clan_plugin_cupboards_clan_id',
            '{{%clan_plugin_cupboards}}',
            'clan_id',
            '{{%clans}}',
            'id',
            'SET NULL'
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_clan_plugin_cupboards_clan_id', '{{%clan_plugin_cupboards}}');
        $this->dropForeignKey('fk_clan_plugin_cupboards_server_id', '{{%clan_plugin_cupboards}}');
        $this->dropTable('{{%clan_plugin_cupboards}}');
    }

    private function resolveColumnType(?ColumnSchema $column)
    {
        if ($column === null) {
            return $this->integer();
        }

        switch ($column->type) {
            case Schema::TYPE_BIGINT:
                $builder = $this->bigInteger();
                break;
            case Schema::TYPE_SMALLINT:
                $builder = $this->smallInteger();
                break;
            case Schema::TYPE_TINYINT:
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
