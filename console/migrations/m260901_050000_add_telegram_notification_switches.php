<?php

use console\components\migration\Migration;

/**
 * Adds an enabled-by-default notification switch to each Telegram alert bot.
 */
class m260901_050000_add_telegram_notification_switches extends Migration
{
    private const CATEGORIES = [
        'tgbotRedFlag',
        'tgbotReport',
        'tgbotPaymentReport',
        'tgbotPayments',
        'tgbotAlert',
        'tgbotSupportAlert',
    ];

    public function safeUp()
    {
        foreach (self::CATEGORIES as $category) {
            $exists = $this->db->createCommand(
                'SELECT 1 FROM {{%site_settings}} WHERE [[category]] = :category AND [[code]] = :code',
                [
                    'category' => $category,
                    'code' => 'enabled',
                ]
            )->queryScalar();

            if (!$exists) {
                $this->insert('{{%site_settings}}', [
                    'name' => 'Включить уведомления',
                    'category' => $category,
                    'type' => 'checkbox',
                    'value' => '1',
                    'code' => 'enabled',
                ]);
            }
        }
    }

    public function safeDown()
    {
        $this->delete('{{%site_settings}}', [
            'and',
            ['code' => 'enabled'],
            ['in', 'category', self::CATEGORIES],
        ]);
    }
}
