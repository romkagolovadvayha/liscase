<?php

use console\components\migration\Migration;

/**
 * Adds concise canonical descriptions used by medal tooltips.
 */
class m260817_030000_add_medal_tooltip_descriptions extends Migration
{
    private const ANNUAL_DESCRIPTIONS = [
        'annual_playtime_2021' => [
            'new' => 'За активную игру на проекте в 2021 году: суммарно более 500 минут игрового времени.',
            'old' => 'Более 500 минут игры на проекте в 2021 году.',
        ],
        'annual_playtime_2022' => [
            'new' => 'За активную игру на проекте в 2022 году: суммарно более 500 минут игрового времени.',
            'old' => 'Более 500 минут игры на проекте в 2022 году.',
        ],
        'annual_playtime_2024' => [
            'new' => 'За активную игру на проекте в 2024 году: суммарно более 500 минут игрового времени.',
            'old' => 'Более 500 минут игры на проекте в 2024 году.',
        ],
        'annual_playtime_2025' => [
            'new' => 'За активную игру на проекте в 2025 году: суммарно более 500 минут игрового времени.',
            'old' => 'Более 500 минут игры на проекте в 2025 году.',
        ],
        'annual_playtime_2026' => [
            'new' => 'За активную игру на проекте в 2026 году: суммарно более 500 минут игрового времени.',
            'old' => 'Более 500 минут игры на проекте в 2026 году.',
        ],
    ];

    private const BATTLE_PASS_DESCRIPTION = [
        'new' => 'За прохождение 80 заданий бесплатной дорожки первого сезона Battle Pass.',
        'old' => 'Медаль за прохождение бесплатной дорожки BATTLE PASS — СЕЗОН 1.',
    ];

    public function safeUp()
    {
        $this->setDescriptions('new');
    }

    public function safeDown()
    {
        $this->setDescriptions('old');
    }

    private function setDescriptions(string $version): void
    {
        $codes = array_keys(self::ANNUAL_DESCRIPTIONS);
        $params = [];
        $placeholders = [];
        foreach ($codes as $index => $code) {
            $placeholder = ':code' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $code;
        }
        $existingCodes = $this->db->createCommand(
            'SELECT `code` FROM `medal` WHERE `code` IN ('
            . implode(', ', $placeholders)
            . ')',
            $params
        )->queryColumn();
        if (count($existingCodes) !== count($codes)) {
            throw new RuntimeException('Not all annual playtime medals were found.');
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::ANNUAL_DESCRIPTIONS as $code => $descriptions) {
            $this->update('medal', [
                'description' => $descriptions[$version],
                'updated_at' => $now,
            ], ['code' => $code]);
        }

        $battlePassMedalId = $this->db->createCommand(
            "SELECT medal_id FROM battle_pass_season WHERE slug = 'season-1'"
        )->queryScalar();
        if (!$battlePassMedalId) {
            throw new RuntimeException('Battle Pass Season 1 medal was not found.');
        }

        $this->update('medal', [
            'description' => self::BATTLE_PASS_DESCRIPTION[$version],
            'updated_at' => $now,
        ], ['id' => (int)$battlePassMedalId]);
    }
}
