<?php

use console\components\migration\Migration;

/**
 * Добавляет пользователя сайта, от которого идут ответы MAX без персональной привязки.
 */
class m260901_040000_add_max_support_default_operator extends Migration
{
    public function safeUp()
    {
        $exists = $this->db->createCommand(
            'SELECT 1 FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
            [
                'category' => 'maxSupport',
                'code' => 'defaultOperatorSteamId',
            ]
        )->queryScalar();

        if (!$exists) {
            $this->insert('{{%site_settings}}', [
                'name' => 'Steam ID пользователя для ответов без персональной привязки',
                'category' => 'maxSupport',
                'type' => 'text',
                'value' => '777',
                'code' => 'defaultOperatorSteamId',
            ]);
        }
    }

    public function safeDown()
    {
        $this->delete('{{%site_settings}}', [
            'category' => 'maxSupport',
            'code' => 'defaultOperatorSteamId',
        ]);
    }
}
