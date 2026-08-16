<?php

namespace common\services\medals;

use common\models\medals\UserMedal;
use RuntimeException;
use yii\db\Connection;

/**
 * Bulk-awards annual veteran medals from the large statistics table.
 */
final class AnnualPlaytimeMedalAwarder
{
    public const MINIMUM_PLAYTIME_MINUTES = 500;
    public const STATISTICS_INDEX = 'idx_statistics_key_wipe_steam_value';
    public const SUPPORTED_YEARS = [2021, 2022, 2024, 2025, 2026];

    /** @var Connection */
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public static function medalCode(int $year): string
    {
        return 'annual_playtime_' . $year;
    }

    /**
     * @return array<int, int> Number of newly awarded medals by year.
     */
    public function awardConfiguredYears(?int $onlyYear = null): array
    {
        $years = $onlyYear === null ? self::SUPPORTED_YEARS : [$onlyYear];
        $result = [];

        foreach ($years as $year) {
            $medalId = $this->getActiveMedalId($year);
            $result[$year] = $this->awardYear($year, $medalId);
        }

        return $result;
    }

    /**
     * Performs the whole award operation in MySQL, without loading recipients into PHP memory.
     * INSERT IGNORE plus the unique user_medal(user_id, medal_id) index makes reruns safe.
     */
    public function awardYear(
        int $year,
        int $medalId,
        int $minimumMinutes = self::MINIMUM_PLAYTIME_MINUTES
    ): int {
        $this->validateArguments($year, $medalId, $minimumMinutes);
        [$startDate, $endDate] = $this->yearBounds($year);
        $now = date('Y-m-d H:i:s');

        $sql = <<<'SQL'
INSERT IGNORE INTO `user_medal`
    (`user_id`, `medal_id`, `source_type`, `source_id`, `note`, `awarded_by_user_id`, `awarded_at`, `created_at`)
SELECT STRAIGHT_JOIN
    u.id,
    :medalId,
    :sourceType,
    :year,
    :note,
    NULL,
    :awardedAt,
    :createdAt
FROM `statistics` s FORCE INDEX (`idx_statistics_key_wipe_steam_value`)
INNER JOIN `user` u
    ON u.steam_id = CONVERT(s.steam_id USING utf8) COLLATE utf8_unicode_ci
WHERE s.`key` = 'playtime'
  AND s.wipe >= :startDate
  AND s.wipe < :endDate
GROUP BY u.id
HAVING SUM(s.value) > :minimumMinutes
SQL;

        return $this->db->createCommand($sql, [
            ':medalId' => $medalId,
            ':sourceType' => UserMedal::SOURCE_ANNUAL_PLAYTIME,
            ':year' => $year,
            ':note' => sprintf('Суммарно более %d минут игры в %d году', $minimumMinutes, $year),
            ':awardedAt' => $now,
            ':createdAt' => $now,
            ':startDate' => $startDate,
            ':endDate' => $endDate,
            ':minimumMinutes' => $minimumMinutes,
        ])->execute();
    }

    /**
     * Counts recipients who qualify and do not have this medal yet (dry-run).
     */
    public function countAwardableForYear(
        int $year,
        int $medalId,
        int $minimumMinutes = self::MINIMUM_PLAYTIME_MINUTES
    ): int {
        $this->validateArguments($year, $medalId, $minimumMinutes);
        [$startDate, $endDate] = $this->yearBounds($year);

        $sql = <<<'SQL'
SELECT COUNT(*)
FROM (
    SELECT STRAIGHT_JOIN u.id
    FROM `statistics` s FORCE INDEX (`idx_statistics_key_wipe_steam_value`)
    INNER JOIN `user` u
        ON u.steam_id = CONVERT(s.steam_id USING utf8) COLLATE utf8_unicode_ci
    LEFT JOIN `user_medal` um
        ON um.user_id = u.id AND um.medal_id = :medalId
    WHERE s.`key` = 'playtime'
      AND s.wipe >= :startDate
      AND s.wipe < :endDate
      AND um.id IS NULL
    GROUP BY u.id
    HAVING SUM(s.value) > :minimumMinutes
) awardable
SQL;

        return (int)$this->db->createCommand($sql, [
            ':medalId' => $medalId,
            ':startDate' => $startDate,
            ':endDate' => $endDate,
            ':minimumMinutes' => $minimumMinutes,
        ])->queryScalar();
    }

    public function getActiveMedalId(int $year): int
    {
        $medalId = $this->db->createCommand(
            'SELECT id FROM `medal` WHERE `code` = :code AND `is_active` = 1',
            [':code' => self::medalCode($year)]
        )->queryScalar();

        if (!$medalId) {
            throw new RuntimeException("Active annual playtime medal for {$year} was not found.");
        }

        return (int)$medalId;
    }

    /** @return string[] */
    private function yearBounds(int $year): array
    {
        return [sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)];
    }

    private function validateArguments(int $year, int $medalId, int $minimumMinutes): void
    {
        if ($year < 2000 || $year > 9998) {
            throw new \InvalidArgumentException('Year must contain four digits.');
        }
        if ($medalId <= 0) {
            throw new \InvalidArgumentException('Medal ID must be positive.');
        }
        if ($minimumMinutes < 0) {
            throw new \InvalidArgumentException('Minimum playtime cannot be negative.');
        }
    }
}
