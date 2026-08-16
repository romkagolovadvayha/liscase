<?php

use console\components\migration\Migration;

/**
 * Replaces the temporary Season 1 medal artwork with the final Battle Pass asset.
 */
class m260814_120000_set_season_one_medal_image extends Migration
{
    private const SEASON_SLUG = 'season-1';
    private const IMAGE_PATH = '/images/battlepass/season-1-medal-v2.webp';
    private const PREVIOUS_IMAGE_PATH = '/images/awards/award1.png';

    public function safeUp()
    {
        $medalId = $this->findSeasonMedalId();

        $this->update('medal', [
            'image_path' => self::IMAGE_PATH,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $medalId]);
    }

    public function safeDown()
    {
        $medalId = $this->findSeasonMedalId();

        $this->update('medal', [
            'image_path' => self::PREVIOUS_IMAGE_PATH,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $medalId]);
    }

    private function findSeasonMedalId(): int
    {
        $medalId = $this->db->createCommand(
            'SELECT medal_id FROM battle_pass_season WHERE slug = :slug',
            [':slug' => self::SEASON_SLUG]
        )->queryScalar();

        if (!$medalId) {
            throw new RuntimeException('Season 1 medal was not found.');
        }

        return (int)$medalId;
    }
}
