<?php

use console\components\migration\Migration;

/**
 * Добавляет общий и независимые выключатели использования прокси.
 */
class m260901_060000_add_proxy_enabled_settings extends Migration
{
    public function safeUp()
    {
        $rows = [
            ['name' => 'Использовать прокси — общий выключатель', 'code' => 'enabled'],
            ['name' => 'Прокси для ChatGPT в поддержке сайта', 'code' => 'openai_support_enabled'],
            ['name' => 'Прокси для ChatGPT в Discord', 'code' => 'openai_discord_enabled'],
            ['name' => 'Прокси для модерации игрового чата', 'code' => 'openai_chat_enabled'],
            ['name' => 'Прокси для генерации викторины', 'code' => 'openai_quiz_enabled'],
            ['name' => 'Прокси для автокомментариев', 'code' => 'openai_comment_enabled'],
            ['name' => 'Прокси для подготовки постов VK', 'code' => 'openai_vk_post_enabled'],
            ['name' => 'Прокси для подготовки постов Telegram', 'code' => 'openai_telegram_post_enabled'],
            ['name' => 'Прокси для переводов через ChatGPT', 'code' => 'openai_translation_enabled'],
            ['name' => 'Прокси для Telegram API', 'code' => 'telegram_enabled'],
            ['name' => 'Прокси для получения метаданных видео', 'code' => 'video_metadata_enabled'],
            ['name' => 'Прокси для загрузки изображений карт', 'code' => 'map_enabled'],
        ];

        foreach ($rows as $row) {
            $exists = $this->db->createCommand(
                'SELECT 1 FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
                ['category' => 'proxy', 'code' => $row['code']]
            )->queryScalar();

            if (!$exists) {
                $this->insert('{{%site_settings}}', [
                    'name' => $row['name'],
                    'category' => 'proxy',
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
            'category' => 'proxy',
            'code' => [
                'enabled',
                'openai_support_enabled',
                'openai_discord_enabled',
                'openai_chat_enabled',
                'openai_quiz_enabled',
                'openai_comment_enabled',
                'openai_vk_post_enabled',
                'openai_telegram_post_enabled',
                'openai_translation_enabled',
                'telegram_enabled',
                'video_metadata_enabled',
                'map_enabled',
            ],
        ]);
    }
}
