<?php

use console\components\migration\Migration;

/**
 * Добавляет отдельную категорию настроек MAX-бота поддержки.
 */
class m260901_030000_add_max_support_settings extends Migration
{
    public function safeUp()
    {
        $rows = [
            [
                'name' => 'Включить уведомления и ответы через MAX',
                'category' => 'maxSupport',
                'type' => 'checkbox',
                'value' => '0',
                'code' => 'enabled',
            ],
            [
                'name' => 'MAX Bot API access token',
                'category' => 'maxSupport',
                'type' => 'password',
                'value' => '',
                'code' => 'accessToken',
            ],
            [
                'name' => 'ID MAX-чата поддержки (пустой заполнится автоматически после добавления бота)',
                'category' => 'maxSupport',
                'type' => 'text',
                'value' => '',
                'code' => 'chatId',
            ],
            [
                'name' => 'Секрет webhook MAX (5–256 символов: A–Z, a–z, 0–9, _ и -)',
                'category' => 'maxSupport',
                'type' => 'password',
                'value' => '',
                'code' => 'webhookSecret',
            ],
            [
                'name' => 'Сотрудники: MAX_ID:ID пользователя сайта — по одной строке или JSON {"MAX_ID": ID}',
                'category' => 'maxSupport',
                'type' => 'longtext',
                'value' => '{}',
                'code' => 'operatorMap',
            ],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->createCommand(
                'SELECT 1 FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
                ['category' => $row['category'], 'code' => $row['code']]
            )->queryScalar();
            if (!$exists) {
                $this->insert('{{%site_settings}}', $row);
            }
        }
    }

    public function safeDown()
    {
        $this->delete('{{%site_settings}}', [
            'and',
            ['category' => 'maxSupport'],
            ['in', 'code', ['enabled', 'accessToken', 'chatId', 'webhookSecret', 'operatorMap']],
        ]);
    }
}
