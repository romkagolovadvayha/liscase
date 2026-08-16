<?php

use console\components\migration\Migration;

/**
 * Gives annual medals distinct frames/colors and labels the Season 1 Battle Pass medal.
 */
class m260817_001000_vary_annual_medals_and_label_battle_pass extends Migration
{
    private const ANNUAL_PATHS = [
        'annual_playtime_2021' => [
            'new' => '/images/awards/veteran-2021-v3.webp',
            'old' => '/images/awards/veteran-2021-v2.webp',
        ],
        'annual_playtime_2022' => [
            'new' => '/images/awards/veteran-2022-v3.webp',
            'old' => '/images/awards/veteran-2022-v2.webp',
        ],
        'annual_playtime_2024' => [
            'new' => '/images/awards/veteran-2024-v3.webp',
            'old' => '/images/awards/veteran-2024-v2.webp',
        ],
        'annual_playtime_2025' => [
            'new' => '/images/awards/veteran-2025-v3.webp',
            'old' => '/images/awards/veteran-2025-v2.webp',
        ],
        'annual_playtime_2026' => [
            'new' => '/images/awards/veteran-2026-v3.webp',
            'old' => '/images/awards/veteran-2026-v2.webp',
        ],
    ];

    private const BATTLE_PASS_NEW_PATH = '/images/battlepass/season-1-medal-v4.webp';
    private const BATTLE_PASS_OLD_PATH = '/images/battlepass/season-1-medal-v3.webp';

    public function safeUp()
    {
        $this->setPaths('new', self::BATTLE_PASS_NEW_PATH);
    }

    public function safeDown()
    {
        $this->setPaths('old', self::BATTLE_PASS_OLD_PATH);
    }

    private function setPaths(string $annualVersion, string $battlePassPath): void
    {
        $codes = array_keys(self::ANNUAL_PATHS);
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
        foreach (self::ANNUAL_PATHS as $code => $paths) {
            $this->update('medal', [
                'image_path' => $paths[$annualVersion],
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
            'image_path' => $battlePassPath,
            'updated_at' => $now,
        ], ['id' => (int)$battlePassMedalId]);
    }
}
