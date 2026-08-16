<?php

use console\components\migration\Migration;

/**
 * Switches Season 1 to the brighter non-circular Battle Pass medal artwork.
 */
class m260814_122000_redraw_season_one_medal extends Migration
{
    private const IMAGE_PATH = '/images/battlepass/season-1-medal-v2.webp';
    private const PREVIOUS_IMAGE_PATH = '/images/battlepass/season-1-medal.webp';

    public function safeUp()
    {
        $this->setImagePath(self::IMAGE_PATH);
    }

    public function safeDown()
    {
        $this->setImagePath(self::PREVIOUS_IMAGE_PATH);
    }

    private function setImagePath(string $imagePath): void
    {
        $medalId = $this->db->createCommand(
            "SELECT medal_id FROM battle_pass_season WHERE slug = 'season-1'"
        )->queryScalar();
        if (!$medalId) {
            throw new RuntimeException('Season 1 medal was not found.');
        }

        $this->update('medal', [
            'image_path' => $imagePath,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int)$medalId]);
    }
}
