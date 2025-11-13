<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%map_list}}`.
 */
class m251113_000001_extend_map_list_table extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%map_list}}');

        $this->execute('ALTER TABLE {{%map_list}} ENGINE=InnoDB');

        if ($schema && !$schema->getColumn('size_int')) {
            $this->addColumn('{{%map_list}}', 'size_int', $this->integer()->after('size'));
        }
        if ($schema && !$schema->getColumn('map_type')) {
            $this->addColumn('{{%map_list}}', 'map_type', $this->string(50)->after($schema->getColumn('size_int') ? 'size_int' : 'size'));
        }
        if ($schema && !$schema->getColumn('seed')) {
            $this->addColumn('{{%map_list}}', 'seed', $this->integer()->after('map_type'));
        }
        if ($schema && !$schema->getColumn('save_version')) {
            $this->addColumn('{{%map_list}}', 'save_version', $this->integer()->after('seed'));
        }

        if ($schema && !$schema->getColumn('raw_image_url')) {
            $this->addColumn('{{%map_list}}', 'raw_image_url', $this->string()->after('image_preview'));
        }
        if ($schema && !$schema->getColumn('image_url')) {
            $this->addColumn('{{%map_list}}', 'image_url', $this->string()->after('raw_image_url'));
        }
        if ($schema && !$schema->getColumn('image_icon_url')) {
            $this->addColumn('{{%map_list}}', 'image_icon_url', $this->string()->after('image_url'));
        }
        if ($schema && !$schema->getColumn('thumbnail_url')) {
            $this->addColumn('{{%map_list}}', 'thumbnail_url', $this->string()->after('image_icon_url'));
        }

        if ($schema && !$schema->getColumn('is_staging')) {
            $this->addColumn('{{%map_list}}', 'is_staging', $this->boolean()->after('thumbnail_url'));
        }
        if ($schema && !$schema->getColumn('is_custom_map')) {
            $this->addColumn('{{%map_list}}', 'is_custom_map', $this->boolean()->after('is_staging'));
        }
        if ($schema && !$schema->getColumn('can_download')) {
            $this->addColumn('{{%map_list}}', 'can_download', $this->boolean()->after('is_custom_map'));
        }

        if ($schema && !$schema->getColumn('total_monuments')) {
            $this->addColumn('{{%map_list}}', 'total_monuments', $this->integer()->after('can_download'));
        }
        if ($schema && !$schema->getColumn('monuments_json')) {
            $this->addColumn('{{%map_list}}', 'monuments_json', $this->text()->after('total_monuments'));
        }

        if ($schema && !$schema->getColumn('land_percentage')) {
            $this->addColumn('{{%map_list}}', 'land_percentage', $this->integer()->after('monuments_json'));
        }
        if ($schema && !$schema->getColumn('biome_percentages_json')) {
            $this->addColumn('{{%map_list}}', 'biome_percentages_json', $this->text()->after('land_percentage'));
        }

        if ($schema && !$schema->getColumn('islands')) {
            $this->addColumn('{{%map_list}}', 'islands', $this->integer()->after('biome_percentages_json'));
        }
        if ($schema && !$schema->getColumn('mountains')) {
            $this->addColumn('{{%map_list}}', 'mountains', $this->integer()->after('islands'));
        }
        if ($schema && !$schema->getColumn('ice_lakes')) {
            $this->addColumn('{{%map_list}}', 'ice_lakes', $this->integer()->after('mountains'));
        }
        if ($schema && !$schema->getColumn('rivers')) {
            $this->addColumn('{{%map_list}}', 'rivers', $this->integer()->after('ice_lakes'));
        }
        if ($schema && !$schema->getColumn('lakes')) {
            $this->addColumn('{{%map_list}}', 'lakes', $this->integer()->after('rivers'));
        }
        if ($schema && !$schema->getColumn('canyons')) {
            $this->addColumn('{{%map_list}}', 'canyons', $this->integer()->after('lakes'));
        }
        if ($schema && !$schema->getColumn('oases')) {
            $this->addColumn('{{%map_list}}', 'oases', $this->integer()->after('canyons'));
        }
        if ($schema && !$schema->getColumn('buildable_rocks')) {
            $this->addColumn('{{%map_list}}', 'buildable_rocks', $this->integer()->after('oases'));
        }

        if ($schema && !$schema->getColumn('data_json')) {
            $this->addColumn('{{%map_list}}', 'data_json', $this->text()->after('buildable_rocks'));
        }
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%map_list}}');
        if (!$schema) {
            return;
        }

        $columns = [
            'data_json',
            'buildable_rocks',
            'oases',
            'canyons',
            'lakes',
            'rivers',
            'ice_lakes',
            'mountains',
            'islands',
            'biome_percentages_json',
            'land_percentage',
            'monuments_json',
            'total_monuments',
            'can_download',
            'is_custom_map',
            'is_staging',
            'thumbnail_url',
            'image_icon_url',
            'image_url',
            'raw_image_url',
            'save_version',
            'seed',
            'map_type',
            'size_int',
        ];

        foreach ($columns as $column) {
            if ($schema->getColumn($column)) {
                $this->dropColumn('{{%map_list}}', $column);
            }
        }
    }
}

