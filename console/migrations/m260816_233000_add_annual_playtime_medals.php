<?php

use common\services\medals\AnnualPlaytimeMedalAwarder;
use console\components\migration\Migration;

/**
 * Adds annual project-veteran medals and a covering index for bulk playtime aggregation.
 */
class m260816_233000_add_annual_playtime_medals extends Migration
{
    private const STATISTICS_INDEX = 'idx_statistics_key_wipe_steam_value';

    private const MEDALS = [
        2021 => [
            'name' => 'Ветеран проекта — 2021',
            'description' => 'Более 500 минут игры на проекте в 2021 году.',
            'image_path' => '/images/awards/veteran-2021.webp',
        ],
        2022 => [
            'name' => 'Ветеран проекта — 2022',
            'description' => 'Более 500 минут игры на проекте в 2022 году.',
            'image_path' => '/images/awards/veteran-2022.webp',
        ],
        2024 => [
            'name' => 'Ветеран проекта — 2024',
            'description' => 'Более 500 минут игры на проекте в 2024 году.',
            'image_path' => '/images/awards/veteran-2024.webp',
        ],
        2025 => [
            'name' => 'Ветеран проекта — 2025',
            'description' => 'Более 500 минут игры на проекте в 2025 году.',
            'image_path' => '/images/awards/veteran-2025.webp',
        ],
        2026 => [
            'name' => 'Ветеран проекта — 2026',
            'description' => 'Более 500 минут игры на проекте в 2026 году.',
            'image_path' => '/images/awards/veteran-2026.webp',
        ],
    ];

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('medal', true)->getColumn('code') === null) {
            $this->addColumn('medal', 'code', 'VARCHAR(64) DEFAULT NULL AFTER `id`');
        }
        if (!$this->indexExists('medal', 'idx-medal-code')) {
            $this->createIndex('idx-medal-code', 'medal', 'code', true);
        }

        if (!$this->indexExists('statistics', self::STATISTICS_INDEX)) {
            $this->execute(
                'ALTER TABLE `statistics` '
                . 'ADD INDEX `' . self::STATISTICS_INDEX . '` (`key`, `wipe`, `steam_id`, `value`), '
                . 'ALGORITHM=INPLACE, LOCK=NONE'
            );
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::MEDALS as $year => $definition) {
            $code = AnnualPlaytimeMedalAwarder::medalCode($year);
            $values = [
                'code' => AnnualPlaytimeMedalAwarder::medalCode($year),
                'name' => $definition['name'],
                'description' => $definition['description'],
                'image_path' => $definition['image_path'],
                'is_active' => 1,
                'updated_at' => $now,
            ];
            $existingId = $this->db->createCommand(
                'SELECT id FROM `medal` WHERE `code` = :code',
                [':code' => $code]
            )->queryScalar();
            if ($existingId) {
                $this->update('medal', $values, ['id' => (int)$existingId]);
            } else {
                $values['created_at'] = $now;
                $this->insert('medal', $values);
            }
        }
    }

    public function safeDown()
    {
        $codes = array_map(
            static function (int $year): string {
                return AnnualPlaytimeMedalAwarder::medalCode($year);
            },
            array_keys(self::MEDALS)
        );
        $medalIds = (new \yii\db\Query())
            ->select('id')
            ->from('medal')
            ->where(['code' => $codes]);

        $this->delete('user_medal', ['medal_id' => $medalIds]);
        $this->delete('medal', ['code' => $codes]);
        $this->dropIndex(self::STATISTICS_INDEX, 'statistics');
        $this->dropIndex('idx-medal-code', 'medal');
        $this->dropColumn('medal', 'code');
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool)$this->db->createCommand(
            'SELECT 1 FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1',
            [':table' => $table, ':index' => $index]
        )->queryScalar();
    }
}
