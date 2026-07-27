<?php

use common\helpers\SettingsCacheHelper;
use console\components\migration\Migration;

/**
 * Отключает неиспользуемые публичные разделы без удаления пользовательских данных.
 */
class m260724_150000_disable_unused_public_sections extends Migration
{
    private const SECTIONS = [
        'media' => 'Медиа',
        'radio' => 'Радио',
        'skins' => 'Ваши скины',
    ];

    public function safeUp()
    {
        foreach (self::SECTIONS as $code => $name) {
            $id = $this->db->createCommand(
                'SELECT [[id]] FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
                ['category' => 'section', 'code' => $code]
            )->queryScalar();

            if ($id) {
                $this->update('{{%site_settings}}', ['value' => '0'], ['id' => $id]);
                continue;
            }

            $this->insert('{{%site_settings}}', [
                'name' => $name,
                'category' => 'section',
                'type' => 'checkbox',
                'value' => '0',
                'code' => $code,
            ]);
        }

        $this->clearSettingsCache();
    }

    public function safeDown()
    {
        $this->update(
            '{{%site_settings}}',
            ['value' => '1'],
            [
                'and',
                ['category' => 'section'],
                ['in', 'code', ['media', 'radio', 'skins']],
            ]
        );

        $this->clearSettingsCache();
    }

    private function clearSettingsCache(): void
    {
        if (!Yii::$app->has('cache')) {
            return;
        }

        Yii::$app->cache->delete('Settings_getSettings');
        SettingsCacheHelper::clearApiSettingsCache();
        Yii::$app->cache->delete('api_radio_list');
    }
}
