<?php

use yii\db\Migration;

/**
 * Handles adding columns to table `{{%map_list}}`.
 */
class m251113_000001_extend_map_list_table extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%map_list}}', 'map_type', $this->string(50)->after('size'));
        $this->addColumn('{{%map_list}}', 'seed', $this->integer()->after('map_type'));
        $this->addColumn('{{%map_list}}', 'save_version', $this->integer()->after('seed'));

        $this->addColumn('{{%map_list}}', 'raw_image_url', $this->string()->after('image_preview'));
        $this->addColumn('{{%map_list}}', 'image_url', $this->string()->after('raw_image_url'));
        $this->addColumn('{{%map_list}}', 'image_icon_url', $this->string()->after('image_url'));
        $this->addColumn('{{%map_list}}', 'thumbnail_url', $this->string()->after('image_icon_url'));

        $this->addColumn('{{%map_list}}', 'is_staging', $this->boolean()->after('thumbnail_url'));
        $this->addColumn('{{%map_list}}', 'is_custom_map', $this->boolean()->after('is_staging'));
        $this->addColumn('{{%map_list}}', 'can_download', $this->boolean()->after('is_custom_map'));

        $this->addColumn('{{%map_list}}', 'total_monuments', $this->integer()->after('can_download'));
        $this->addColumn('{{%map_list}}', 'monuments_json', $this->text()->after('total_monuments'));

        $this->addColumn('{{%map_list}}', 'land_percentage', $this->integer()->after('monuments_json'));
        $this->addColumn('{{%map_list}}', 'biome_percentages_json', $this->text()->after('land_percentage'));

        $this->addColumn('{{%map_list}}', 'islands', $this->integer()->after('biome_percentages_json'));
        $this->addColumn('{{%map_list}}', 'mountains', $this->integer()->after('islands'));
        $this->addColumn('{{%map_list}}', 'ice_lakes', $this->integer()->after('mountains'));
        $this->addColumn('{{%map_list}}', 'rivers', $this->integer()->after('ice_lakes'));
        $this->addColumn('{{%map_list}}', 'lakes', $this->integer()->after('rivers'));
        $this->addColumn('{{%map_list}}', 'canyons', $this->integer()->after('lakes'));
        $this->addColumn('{{%map_list}}', 'oases', $this->integer()->after('canyons'));
        $this->addColumn('{{%map_list}}', 'buildable_rocks', $this->integer()->after('oases'));

        $this->addColumn('{{%map_list}}', 'data_json', $this->text()->after('buildable_rocks'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%map_list}}', 'data_json');

        $this->dropColumn('{{%map_list}}', 'buildable_rocks');
        $this->dropColumn('{{%map_list}}', 'oases');
        $this->dropColumn('{{%map_list}}', 'canyons');
        $this->dropColumn('{{%map_list}}', 'lakes');
        $this->dropColumn('{{%map_list}}', 'rivers');
        $this->dropColumn('{{%map_list}}', 'ice_lakes');
        $this->dropColumn('{{%map_list}}', 'mountains');
        $this->dropColumn('{{%map_list}}', 'islands');

        $this->dropColumn('{{%map_list}}', 'biome_percentages_json');
        $this->dropColumn('{{%map_list}}', 'land_percentage');

        $this->dropColumn('{{%map_list}}', 'monuments_json');
        $this->dropColumn('{{%map_list}}', 'total_monuments');

        $this->dropColumn('{{%map_list}}', 'can_download');
        $this->dropColumn('{{%map_list}}', 'is_custom_map');
        $this->dropColumn('{{%map_list}}', 'is_staging');

        $this->dropColumn('{{%map_list}}', 'thumbnail_url');
        $this->dropColumn('{{%map_list}}', 'image_icon_url');
        $this->dropColumn('{{%map_list}}', 'image_url');
        $this->dropColumn('{{%map_list}}', 'raw_image_url');

        $this->dropColumn('{{%map_list}}', 'save_version');
        $this->dropColumn('{{%map_list}}', 'seed');
        $this->dropColumn('{{%map_list}}', 'map_type');
    }
}

