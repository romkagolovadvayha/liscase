<?php

use console\components\migration\Migration;

/**
 * Добавляет независимые выключатели запросов к ChatGPT/OpenAI.
 */
class m260901_050000_add_openai_enabled_setting extends Migration
{
    public function safeUp()
    {
        $rows = [
            ['name' => 'ChatGPT в поддержке сайта', 'code' => 'support_enabled'],
            ['name' => 'ChatGPT в Discord', 'code' => 'discord_enabled'],
            ['name' => 'Модерация игрового чата через ChatGPT', 'code' => 'chat_enabled'],
            ['name' => 'Генерация вопросов викторины через ChatGPT', 'code' => 'quiz_enabled'],
            ['name' => 'Переводы через ChatGPT', 'code' => 'translation_enabled'],
            ['name' => 'Генерация контента через ChatGPT', 'code' => 'content_enabled'],
            ['name' => 'Автокомментарии через ChatGPT', 'code' => 'comment_enabled'],
            ['name' => 'Подготовка постов VK через ChatGPT', 'code' => 'vk_post_enabled'],
            ['name' => 'Подготовка постов Telegram через ChatGPT', 'code' => 'telegram_post_enabled'],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->createCommand(
                'SELECT 1 FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
                ['category' => 'openAi', 'code' => $row['code']]
            )->queryScalar();

            if (!$exists) {
                $this->insert('{{%site_settings}}', [
                    'name' => $row['name'],
                    'category' => 'openAi',
                    'type' => 'checkbox',
                    'value' => '1',
                    'code' => $row['code'],
                ]);
            }
        }
    }

    public function safeDown()
    {
        $this->delete('{{%site_settings}}', [
            'category' => 'openAi',
            'code' => [
                'support_enabled',
                'discord_enabled',
                'chat_enabled',
                'quiz_enabled',
                'translation_enabled',
                'content_enabled',
                'vk_post_enabled',
                'telegram_post_enabled',
            ],
        ]);

        // comment_enabled существовал до этой миграции и может использоваться
        // текущей установкой, поэтому при откате его не удаляем.
    }
}
