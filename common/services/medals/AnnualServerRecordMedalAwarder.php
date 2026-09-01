<?php

namespace common\services\medals;

use common\models\medals\UserMedal;
use common\models\user\UserTop;
use DateTimeImmutable;
use InvalidArgumentException;
use yii\db\Connection;

/**
 * Awards first-place annual record medals for every server and Records category.
 *
 * The large statistics table is aggregated once per year. Only the small set of
 * final winners is then resolved to site users and written to user_medal.
 */
final class AnnualServerRecordMedalAwarder
{
    public const STATISTICS_INDEX = 'idx_statistics_key_wipe_server_steam_value';
    public const SUPPORTED_YEARS = [2021, 2022, 2024, 2025, 2026];

    private const CATEGORY_ORDER = [
        UserTop::TYPE_FARMER,
        UserTop::TYPE_REIDER,
        UserTop::TYPE_FERMER,
        UserTop::TYPE_HUNTER,
        UserTop::TYPE_FISHING,
        UserTop::TYPE_PLAYTIME,
        UserTop::TYPE_KILLS,
        UserTop::TYPE_SCIENTISTS,
    ];

    private const CATEGORY_TITLES = [
        UserTop::TYPE_FARMER => 'Лучший фармер',
        UserTop::TYPE_REIDER => 'Лучший рейдер',
        UserTop::TYPE_FERMER => 'Лучший фермер',
        UserTop::TYPE_HUNTER => 'Лучший охотник',
        UserTop::TYPE_FISHING => 'Лучший рыбак',
        UserTop::TYPE_PLAYTIME => 'Топ по онлайну',
        UserTop::TYPE_KILLS => 'Лучший киллер',
        UserTop::TYPE_SCIENTISTS => 'Лучший мирный',
    ];

    private const CATEGORY_DESCRIPTIONS = [
        UserTop::TYPE_FARMER => 'первое место по добыче ресурсов',
        UserTop::TYPE_REIDER => 'первое место по рейдам',
        UserTop::TYPE_FERMER => 'первое место по выращиванию и сбору урожая',
        UserTop::TYPE_HUNTER => 'первое место по охоте',
        UserTop::TYPE_FISHING => 'первое место по рыбалке',
        UserTop::TYPE_PLAYTIME => 'первое место по онлайну',
        UserTop::TYPE_KILLS => 'первое место по убийствам игроков',
        UserTop::TYPE_SCIENTISTS => 'первое место по убийствам NPC-учёных',
    ];

    private const CATEGORY_IMAGES = [
        UserTop::TYPE_FARMER => '/images/awards/sticker-style/record-resource-farmer.webp',
        UserTop::TYPE_REIDER => '/images/awards/sticker-style/record-raider.webp',
        UserTop::TYPE_FERMER => '/images/awards/sticker-style/record-crop-farmer.webp',
        UserTop::TYPE_HUNTER => '/images/awards/sticker-style/record-hunter.webp',
        UserTop::TYPE_FISHING => '/images/awards/sticker-style/record-fishing.webp',
        UserTop::TYPE_PLAYTIME => '/images/awards/sticker-style/record-playtime.webp',
        UserTop::TYPE_KILLS => '/images/awards/sticker-style/record-kills.webp',
        UserTop::TYPE_SCIENTISTS => '/images/awards/sticker-style/record-scientists.webp',
    ];

    /** @var Connection */
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Runs all configured years that have ended. An explicitly selected current
     * year still requires $allowIncomplete=true.
     *
     * @return array<int, array<string, mixed>>
     */
    public function awardConfiguredCompletedYears(
        ?int $onlyYear = null,
        bool $allowIncomplete = false,
        bool $dryRun = false
    ): array {
        $years = $onlyYear === null
            ? array_values(array_filter(self::SUPPORTED_YEARS, static function (int $year): bool {
                return $year < (int)date('Y');
            }))
            : [$onlyYear];

        $reports = [];
        foreach ($years as $year) {
            $reports[$year] = $dryRun
                ? $this->previewYear($year, $allowIncomplete)
                : $this->awardYear($year, $allowIncomplete);
        }

        return $reports;
    }

    /**
     * Calculates winners without changing medal or user_medal.
     *
     * @return array<string, mixed>
     */
    public function previewYear(int $year, bool $allowIncomplete = false): array
    {
        $this->validateYear($year, $allowIncomplete);
        $winners = $this->findWinners($year);
        $resolved = $this->resolveWinnerUsers($winners);
        $alreadyAwarded = $this->countExistingAwards($year, $resolved);

        return $this->buildReport(
            $year,
            $winners,
            $resolved,
            count($resolved) - $alreadyAwarded,
            $alreadyAwarded,
            true
        );
    }

    /**
     * Calculates, creates definitions and awards all record medals for one year.
     * Repeated calls are safe because medal.code and user_medal(user_id, medal_id)
     * are unique, while INSERT IGNORE only counts genuinely new awards.
     *
     * @return array<string, mixed>
     */
    public function awardYear(int $year, bool $allowIncomplete = false): array
    {
        $this->validateYear($year, $allowIncomplete);
        $winners = $this->findWinners($year);
        $resolved = $this->resolveWinnerUsers($winners);
        $awardRows = [];
        $now = date('Y-m-d H:i:s');

        $transaction = $this->db->beginTransaction();
        try {
            foreach ($resolved as $winner) {
                $definition = $this->medalDefinition(
                    $year,
                    $winner['server_tag'],
                    $winner['server_name'],
                    $winner['category']
                );
                $medalId = $this->upsertMedal($definition, $now);
                $awardRows[] = [
                    (int)$winner['user_id'],
                    $medalId,
                    UserMedal::SOURCE_ANNUAL_SERVER_RECORD,
                    $year,
                    $this->awardNote($year, $winner),
                    null,
                    $now,
                    $now,
                ];
            }

            $awarded = $this->insertAwards($awardRows);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $this->buildReport(
            $year,
            $winners,
            $resolved,
            $awarded,
            count($resolved) - $awarded,
            false
        );
    }

    /**
     * @return array<string, array<string, array<string, int|float|string>>>
     */
    public function findWinners(int $year): array
    {
        [$startDate, $endDate] = $this->yearBounds($year);
        $weights = $this->metricWeights();
        $allKeys = [];
        $scoreExpressions = [];
        foreach (self::CATEGORY_ORDER as $category) {
            foreach ($weights[$category] as $key => $_weight) {
                $allKeys[$key] = true;
            }
            $scoreExpressions[] = $this->scoreExpression($category, $weights[$category]);
        }

        $quotedKeys = array_map([$this->db, 'quoteValue'], array_keys($allKeys));
        $sql = 'SELECT s.`server_tag`, s.`steam_id`, '
            . implode(', ', $scoreExpressions)
            . ' FROM `statistics` s FORCE INDEX (`' . self::STATISTICS_INDEX . '`)'
            . ' WHERE s.`key` IN (' . implode(', ', $quotedKeys) . ')'
            . ' AND s.`wipe` >= :startDate AND s.`wipe` < :endDate'
            . " AND s.`server_tag` IS NOT NULL AND s.`server_tag` <> ''"
            . " AND s.`steam_id` IS NOT NULL AND s.`steam_id` <> ''"
            . ' GROUP BY s.`server_tag`, s.`steam_id`';

        $winners = [];
        $reader = $this->db->createCommand($sql, [
            ':startDate' => $startDate,
            ':endDate' => $endDate,
        ])->query();
        try {
            foreach ($reader as $row) {
                $serverTag = trim((string)$row['server_tag']);
                $steamId = (string)$row['steam_id'];
                $playtime = (float)$row[UserTop::TYPE_PLAYTIME];
                foreach (self::CATEGORY_ORDER as $category) {
                    $score = (float)$row[$category];
                    if ($score <= 0) {
                        continue;
                    }
                    $candidate = [
                        'server_tag' => $serverTag,
                        'steam_id' => $steamId,
                        'category' => $category,
                        'score' => $score,
                        'playtime' => $playtime,
                    ];
                    $current = $winners[$serverTag][$category] ?? null;
                    if ($current === null || $this->isBetterCandidate($candidate, $current)) {
                        $winners[$serverTag][$category] = $candidate;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        ksort($winners, SORT_NATURAL | SORT_FLAG_CASE);
        return $winners;
    }

    public static function medalCode(int $year, string $serverTag, string $category): string
    {
        $slug = strtolower(trim($serverTag));
        $slug = preg_replace('~[^a-z0-9]+~', '-', $slug) ?: 'server';
        $slug = trim(substr($slug, 0, 16), '-');
        return sprintf(
            'annual_record_%d_%s-%s_%s',
            $year,
            $slug ?: 'server',
            substr(sha1(strtolower(trim($serverTag))), 0, 6),
            $category
        );
    }

    /** @return array<string, array<string, float>> */
    private function metricWeights(): array
    {
        $weights = UserTop::getRaiting();
        // The Records cache treats legacy `deer` as the same hunting stat as `stag`.
        $weights[UserTop::TYPE_HUNTER]['deer'] = 1.0;
        return $weights;
    }

    /** @param array<string, int|float> $weights */
    private function scoreExpression(string $alias, array $weights): string
    {
        $cases = [];
        foreach ($weights as $key => $weight) {
            $numericWeight = rtrim(rtrim(sprintf('%.8F', (float)$weight), '0'), '.');
            $cases[] = 'WHEN ' . $this->db->quoteValue($key) . ' THEN ' . $numericWeight;
        }

        return 'ROUND(SUM(CAST(s.`value` AS DECIMAL(24,4)) * CASE s.`key` '
            . implode(' ', $cases)
            . ' ELSE 0 END), 2) AS `' . $alias . '`';
    }

    /**
     * @param array<string, int|float|string> $candidate
     * @param array<string, int|float|string> $current
     */
    private function isBetterCandidate(array $candidate, array $current): bool
    {
        if ((float)$candidate['score'] !== (float)$current['score']) {
            return (float)$candidate['score'] > (float)$current['score'];
        }
        if ((float)$candidate['playtime'] !== (float)$current['playtime']) {
            return (float)$candidate['playtime'] > (float)$current['playtime'];
        }
        return strcmp((string)$candidate['steam_id'], (string)$current['steam_id']) < 0;
    }

    /**
     * @param array<string, array<string, array<string, int|float|string>>> $winners
     * @return array<int, array<string, int|float|string>>
     */
    private function resolveWinnerUsers(array $winners): array
    {
        $steamIds = [];
        foreach ($winners as $serverWinners) {
            foreach ($serverWinners as $winner) {
                $steamIds[(string)$winner['steam_id']] = true;
            }
        }
        if ($steamIds === []) {
            return [];
        }

        $usersBySteam = [];
        foreach (array_chunk(array_keys($steamIds), 400) as $chunk) {
            $rows = (new \yii\db\Query())
                ->select(['user_id' => 'MIN(id)', 'steam_id'])
                ->from('user')
                ->where(['steam_id' => $chunk, 'is_stats' => 1])
                ->groupBy('steam_id')
                ->all($this->db);
            foreach ($rows as $row) {
                $usersBySteam[(string)$row['steam_id']] = (int)$row['user_id'];
            }
        }

        $serverNames = $this->loadServerNames(array_keys($winners));
        $resolved = [];
        foreach ($winners as $serverTag => $serverWinners) {
            foreach ($serverWinners as $category => $winner) {
                $steamId = (string)$winner['steam_id'];
                if (!isset($usersBySteam[$steamId])) {
                    continue;
                }
                $winner['user_id'] = $usersBySteam[$steamId];
                $winner['server_name'] = $serverNames[$serverTag] ?? strtoupper($serverTag);
                $resolved[] = $winner;
            }
        }

        return $resolved;
    }

    /** @param string[] $serverTags @return array<string, string> */
    private function loadServerNames(array $serverTags): array
    {
        if ($serverTags === []) {
            return [];
        }
        $rows = (new \yii\db\Query())
            ->select(['tag', 'monitoring_name'])
            ->from('servers')
            ->where(['tag' => $serverTags])
            ->all($this->db);
        $names = [];
        foreach ($rows as $row) {
            $tag = trim((string)$row['tag']);
            $name = trim((string)$row['monitoring_name']);
            $names[$tag] = $name !== '' ? sprintf('%s (%s)', $name, $tag) : strtoupper($tag);
        }
        return $names;
    }

    /** @return array<string, int|string> */
    private function medalDefinition(int $year, string $serverTag, string $serverName, string $category): array
    {
        $title = self::CATEGORY_TITLES[$category];
        return [
            'code' => self::medalCode($year, $serverTag, $category),
            'name' => sprintf('%s — %s, %d', $title, $serverName, $year),
            'description' => sprintf(
                'За %s на сервере %s по итогам %d года.',
                self::CATEGORY_DESCRIPTIONS[$category],
                $serverName,
                $year
            ),
            'image_path' => self::CATEGORY_IMAGES[$category],
            'is_active' => 1,
        ];
    }

    /** @param array<string, int|string> $definition */
    private function upsertMedal(array $definition, string $now): int
    {
        $sql = <<<'SQL'
INSERT INTO `medal` (`code`, `name`, `description`, `image_path`, `is_active`, `created_at`, `updated_at`)
VALUES (:code, :name, :description, :imagePath, :isActive, :createdAt, :updatedAt)
ON DUPLICATE KEY UPDATE
    `id` = LAST_INSERT_ID(`id`),
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `image_path` = VALUES(`image_path`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = VALUES(`updated_at`)
SQL;
        $this->db->createCommand($sql, [
            ':code' => $definition['code'],
            ':name' => $definition['name'],
            ':description' => $definition['description'],
            ':imagePath' => $definition['image_path'],
            ':isActive' => $definition['is_active'],
            ':createdAt' => $now,
            ':updatedAt' => $now,
        ])->execute();

        return (int)$this->db->getLastInsertID();
    }

    /** @param array<int, array<int, int|string|null>> $rows */
    private function insertAwards(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $columns = [
            'user_id', 'medal_id', 'source_type', 'source_id', 'note',
            'awarded_by_user_id', 'awarded_at', 'created_at',
        ];
        $sql = $this->db->queryBuilder->batchInsert('user_medal', $columns, $rows);
        $sql = preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $sql, 1);
        return $this->db->createCommand($sql)->execute();
    }

    /** @param array<int, array<string, int|float|string>> $resolved */
    private function countExistingAwards(int $year, array $resolved): int
    {
        if ($resolved === []) {
            return 0;
        }

        $expected = [];
        foreach ($resolved as $winner) {
            $code = self::medalCode($year, (string)$winner['server_tag'], (string)$winner['category']);
            $expected[$code] = (int)$winner['user_id'];
        }
        $rows = (new \yii\db\Query())
            ->select(['code' => 'm.code', 'user_id' => 'um.user_id'])
            ->from(['m' => 'medal'])
            ->innerJoin(['um' => 'user_medal'], 'um.medal_id = m.id')
            ->where(['m.code' => array_keys($expected)])
            ->all($this->db);

        $count = 0;
        foreach ($rows as $row) {
            $code = (string)$row['code'];
            if (isset($expected[$code]) && $expected[$code] === (int)$row['user_id']) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<string, int|float|string> $winner */
    private function awardNote(int $year, array $winner): string
    {
        $score = in_array($winner['category'], [
            UserTop::TYPE_PLAYTIME,
            UserTop::TYPE_KILLS,
            UserTop::TYPE_SCIENTISTS,
        ], true)
            ? number_format((float)$winner['score'], 0, '.', ' ')
            : number_format((float)$winner['score'], 2, '.', ' ');

        return sprintf(
            '%s, %d год: первое место «%s», результат %s.',
            $winner['server_name'],
            $year,
            self::CATEGORY_TITLES[$winner['category']],
            $score
        );
    }

    /**
     * @param array<string, array<string, array<string, int|float|string>>> $winners
     * @param array<int, array<string, int|float|string>> $resolved
     * @return array<string, mixed>
     */
    private function buildReport(
        int $year,
        array $winners,
        array $resolved,
        int $awarded,
        int $alreadyAwarded,
        bool $dryRun
    ): array
    {
        $winnerCount = 0;
        foreach ($winners as $serverWinners) {
            $winnerCount += count($serverWinners);
        }
        return [
            'year' => $year,
            'servers' => count($winners),
            'winners' => $winnerCount,
            'eligible' => count($resolved),
            'skipped_without_profile' => $winnerCount - count($resolved),
            'awarded' => $awarded,
            'already_awarded' => $alreadyAwarded,
            'dry_run' => $dryRun,
        ];
    }

    /** @return string[] */
    private function yearBounds(int $year): array
    {
        return [sprintf('%04d-01-01', $year), sprintf('%04d-01-01', $year + 1)];
    }

    private function validateYear(int $year, bool $allowIncomplete): void
    {
        if (!in_array($year, self::SUPPORTED_YEARS, true)) {
            throw new InvalidArgumentException('Supported years: ' . implode(', ', self::SUPPORTED_YEARS));
        }
        $currentYear = (int)(new DateTimeImmutable('now'))->format('Y');
        if ($year >= $currentYear && !$allowIncomplete) {
            throw new InvalidArgumentException(
                "Year {$year} has not ended yet; pass --allow-incomplete=1 to calculate it explicitly."
            );
        }
    }
}
