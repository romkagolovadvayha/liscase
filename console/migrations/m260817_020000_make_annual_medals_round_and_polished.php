<?php

use console\components\migration\Migration;

/**
 * Switches annual medals to polished circular artwork while keeping Battle Pass hexagonal.
 */
class m260817_020000_make_annual_medals_round_and_polished extends Migration
{
    private const ANNUAL_PATHS = [
        'annual_playtime_2021' => [
            'new' => '/images/awards/veteran-2021-v5.webp',
            'old' => '/images/awards/veteran-2021-v4.webp',
        ],
        'annual_playtime_2022' => [
            'new' => '/images/awards/veteran-2022-v5.webp',
            'old' => '/images/awards/veteran-2022-v4.webp',
        ],
        'annual_playtime_2024' => [
            'new' => '/images/awards/veteran-2024-v5.webp',
            'old' => '/images/awards/veteran-2024-v4.webp',
        ],
        'annual_playtime_2025' => [
            'new' => '/images/awards/veteran-2025-v5.webp',
            'old' => '/images/awards/veteran-2025-v4.webp',
        ],
        'annual_playtime_2026' => [
            'new' => '/images/awards/veteran-2026-v5.webp',
            'old' => '/images/awards/veteran-2026-v4.webp',
        ],
    ];

    public function safeUp()
    {
        $this->setPaths('new');
    }

    public function safeDown()
    {
        $this->setPaths('old');
    }

    private function setPaths(string $version): void
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
                'image_path' => $paths[$version],
                'updated_at' => $now,
            ], ['code' => $code]);
        }
    }
}
