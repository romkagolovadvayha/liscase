<?php

use console\components\migration\Migration;

/**
 * Moves active medals to cache-busting sticker-style asset paths.
 */
class m260901_170000_switch_medals_to_sticker_assets extends Migration
{
    private const ANNUAL_PATHS = [
        'annual_playtime_2021' => [
            'old' => '/images/awards/veteran-2021-v5.webp',
            'new' => '/images/awards/sticker-style/veteran-2021.webp',
        ],
        'annual_playtime_2022' => [
            'old' => '/images/awards/veteran-2022-v5.webp',
            'new' => '/images/awards/sticker-style/veteran-2022.webp',
        ],
        'annual_playtime_2024' => [
            'old' => '/images/awards/veteran-2024-v5.webp',
            'new' => '/images/awards/sticker-style/veteran-2024.webp',
        ],
        'annual_playtime_2025' => [
            'old' => '/images/awards/veteran-2025-v5.webp',
            'new' => '/images/awards/sticker-style/veteran-2025.webp',
        ],
        'annual_playtime_2026' => [
            'old' => '/images/awards/veteran-2026-v5.webp',
            'new' => '/images/awards/sticker-style/veteran-2026.webp',
        ],
    ];

    private const BATTLE_PASS_PATHS = [
        'old' => '/images/battlepass/season-1-medal-v5.webp',
        'new' => '/images/awards/sticker-style/battlepass-season-1.webp',
    ];

    private const RECORD_PATHS = [
        '/images/awards/records/farmer.webp' => '/images/awards/sticker-style/record-resource-farmer.webp',
        '/images/awards/records/reider.webp' => '/images/awards/sticker-style/record-raider.webp',
        '/images/awards/records/fermer.webp' => '/images/awards/sticker-style/record-crop-farmer.webp',
        '/images/awards/records/hunter.webp' => '/images/awards/sticker-style/record-hunter.webp',
        '/images/awards/records/fishing.webp' => '/images/awards/sticker-style/record-fishing.webp',
        '/images/awards/records/playtime.webp' => '/images/awards/sticker-style/record-playtime.webp',
        '/images/awards/records/kills.webp' => '/images/awards/sticker-style/record-kills.webp',
        '/images/awards/records/scientists.webp' => '/images/awards/sticker-style/record-scientists.webp',
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
        $existingCodes = (new \yii\db\Query())
            ->select('code')
            ->from('medal')
            ->where(['code' => $codes])
            ->column($this->db);

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

        $battlePassMedalId = $this->db->createCommand(
            "SELECT medal_id FROM battle_pass_season WHERE slug = 'season-1'"
        )->queryScalar();
        if (!$battlePassMedalId) {
            throw new RuntimeException('Battle Pass Season 1 medal was not found.');
        }

        $this->update('medal', [
            'image_path' => self::BATTLE_PASS_PATHS[$version],
            'updated_at' => $now,
        ], ['id' => (int)$battlePassMedalId]);

        foreach (self::RECORD_PATHS as $oldPath => $newPath) {
            $fromPath = $version === 'new' ? $oldPath : $newPath;
            $toPath = $version === 'new' ? $newPath : $oldPath;
            $this->update('medal', [
                'image_path' => $toPath,
                'updated_at' => $now,
            ], ['image_path' => $fromPath]);
        }
    }
}
