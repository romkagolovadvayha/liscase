<?php

use console\components\migration\Migration;

/**
 * Переключает liscase с RustApp/RustCheatCheck на приватный API Rust Admin.
 */
class m260811_160000_replace_moderation_integrations_with_rust_admin extends Migration
{
    public function safeUp()
    {
        $this->addColumn('servers', 'rust_admin_id', 'VARCHAR(191) DEFAULT NULL AFTER `rust_app_id`');
        $this->createIndex('ux_servers_rust_admin_id', 'servers', 'rust_admin_id', true);
        $this->createIndex('idx_bans_steam_server', 'bans', ['steam_id', 'server_id']);

        $settings = [
            [
                'name' => 'Rust Admin API URL',
                'category' => 'rustAdmin',
                'type' => 'text',
                'value' => 'https://api.rust-admin.ru',
                'code' => 'baseUrl',
            ],
            [
                'name' => 'Rust Admin Private API Key',
                'category' => 'rustAdmin',
                'type' => 'password',
                'value' => 'ra_56dc91a8a5d0bcb2c65545900d477640a5c46a6fdc1fa6dd',
                'code' => 'privateApiKey',
            ],
        ];
        foreach ($settings as $setting) {
            $exists = $this->db->createCommand(
                'SELECT 1 FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
                [':category' => $setting['category'], ':code' => $setting['code']]
            )->queryScalar();
            if (!$exists) {
                $this->insert('{{%site_settings}}', $setting);
            }
        }

        // Старые ключи больше не должны оставаться рабочей конфигурацией.
        $this->delete('{{%site_settings}}', [
            'and',
            ['category' => 'banSystem'],
            ['in', 'code', ['rustAppPrivateApiKey', 'rustcheatcheck']],
        ]);
        $this->dropColumn('servers', 'rust_app_id');
    }

    public function safeDown()
    {
        $this->addColumn('servers', 'rust_app_id', self::INT_FIELD);
        $this->dropIndex('idx_bans_steam_server', 'bans');
        $this->dropIndex('ux_servers_rust_admin_id', 'servers');
        $this->dropColumn('servers', 'rust_admin_id');
        $this->delete('{{%site_settings}}', [
            'and',
            ['category' => 'rustAdmin'],
            ['in', 'code', ['baseUrl', 'privateApiKey']],
        ]);

        // Старые секреты намеренно не восстанавливаются из соображений безопасности.
        foreach ([
            ['name' => 'RustApp Private API Key', 'code' => 'rustAppPrivateApiKey'],
            ['name' => 'RustCheatCheck API Key', 'code' => 'rustcheatcheck'],
        ] as $setting) {
            $this->insert('{{%site_settings}}', [
                'name' => $setting['name'],
                'category' => 'banSystem',
                'type' => 'password',
                'value' => '',
                'code' => $setting['code'],
            ]);
        }
    }
}
