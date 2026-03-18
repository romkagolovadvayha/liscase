<?php

use console\components\migration\Migration;

/**
 * Добавляет настройки Twitch OAuth (client_id, client_secret) в site_settings.
 * Используются для привязки аккаунта Twitch в профиле пользователя.
 */
class m260318_130000_add_twitch_oauth_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $rows = [
            [
                'name' => 'Twitch Client ID',
                'category' => 'twitch',
                'type' => 'text',
                'value' => '',
                'code' => 'client_id',
            ],
            [
                'name' => 'Twitch Client Secret',
                'category' => 'twitch',
                'type' => 'text',
                'value' => '',
                'code' => 'client_secret',
            ],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->createCommand(
                'SELECT 1 FROM {{%site_settings}} WHERE [[category]] = :cat AND [[code]] = :code',
                ['cat' => $row['category'], 'code' => $row['code']]
            )->queryScalar();
            if (!$exists) {
                $this->insert('{{%site_settings}}', $row);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete('{{%site_settings}}', [
            'and',
            ['category' => 'twitch'],
            ['in', 'code', ['client_id', 'client_secret']],
        ]);
    }
}
