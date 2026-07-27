<?php

use common\helpers\SettingsCacheHelper;
use console\components\migration\Migration;

/**
 * Возвращает публичный раздел «Маркет скинов».
 */
class m260727_120000_enable_skin_market extends Migration
{
    public function safeUp()
    {
        $id = $this->db->createCommand(
            'SELECT [[id]] FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
            ['category' => 'section', 'code' => 'market']
        )->queryScalar();

        if ($id) {
            $this->update('{{%site_settings}}', ['value' => '1'], ['id' => $id]);
        } else {
            $this->insert('{{%site_settings}}', [
                'name' => 'Маркет скинов',
                'category' => 'section',
                'type' => 'checkbox',
                'value' => '1',
                'code' => 'market',
            ]);
        }

        $this->clearSettingsCache();
    }

    public function safeDown()
    {
        $this->update(
            '{{%site_settings}}',
            ['value' => '0'],
            ['category' => 'section', 'code' => 'market']
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
    }
}
