<?php

use console\components\migration\Migration;

/**
 * Добавляет настройки Kick.com OAuth (client_id, client_secret) в site_settings.
 * Документация: https://docs.kick.com/ (OAuth 2.1 с PKCE)
 */
class m260318_150000_add_kick_oauth_settings extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $rows = [
            [
                'name' => 'Kick Client ID',
                'category' => 'kick',
                'type' => 'text',
                'value' => '',
                'code' => 'client_id',
            ],
            [
                'name' => 'Kick Client Secret',
                'category' => 'kick',
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
            ['category' => 'kick'],
            ['in', 'code', ['client_id', 'client_secret']],
        ]);
    }
}
