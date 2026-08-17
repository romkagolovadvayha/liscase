<?php

use common\models\servers\Servers;
use common\models\tasks_v2\TaskV2;
use console\components\migration\Migration;
use yii\db\Query;
use yii\helpers\Json;

/**
 * Makes the Battle Pass crate and pumpkin tasks match the events recorded by
 * ExpertStatistics.
 *
 * A task baseline stores the sum of every configured key. When the key set is
 * changed, existing unlocked tasks must be rebased by the same difference;
 * otherwise historical statistics would be granted as fresh progress.
 */
class m260817_164000_fix_battle_pass_statistics_keys extends Migration
{
    private const SEASON_SLUG = 'season-1';

    private const CRATE_POSITIONS = [7, 27, 47, 67, 87];
    private const CRATE_OLD_KEYS = ['crate_open'];
    private const CRATE_NEW_KEYS = [
        'crate_open',
        'crate_normal',
        'crate_elite',
        'crate_underwater_advanced',
        'crate_underwater_basic',
        'supply_drop',
        'codelockedhackablecrate',
        'codelockedhackablecrate_oilrig',
    ];

    private const PUMPKIN_POSITIONS = [15, 35, 55, 75, 95];
    private const PUMPKIN_OLD_KEYS = ['mod_pumpkin'];
    private const PUMPKIN_NEW_KEYS = ['gathered_pumpkin'];

    public function safeUp()
    {
        $season = $this->findSeason();
        $serverTags = $this->findTrackedServerTags();

        foreach (self::CRATE_POSITIONS as $position) {
            $this->changeTaskKeys(
                (int)$season['id'],
                (string)$season['starts_at'],
                $serverTags,
                $position,
                self::CRATE_OLD_KEYS,
                self::CRATE_NEW_KEYS
            );
        }

        foreach (self::PUMPKIN_POSITIONS as $position) {
            $this->changeTaskKeys(
                (int)$season['id'],
                (string)$season['starts_at'],
                $serverTags,
                $position,
                self::PUMPKIN_OLD_KEYS,
                self::PUMPKIN_NEW_KEYS
            );
        }
    }

    public function safeDown()
    {
        $season = $this->findSeason();
        $serverTags = $this->findTrackedServerTags();

        foreach (self::PUMPKIN_POSITIONS as $position) {
            $this->changeTaskKeys(
                (int)$season['id'],
                (string)$season['starts_at'],
                $serverTags,
                $position,
                self::PUMPKIN_NEW_KEYS,
                self::PUMPKIN_OLD_KEYS
            );
        }

        foreach (self::CRATE_POSITIONS as $position) {
            $this->changeTaskKeys(
                (int)$season['id'],
                (string)$season['starts_at'],
                $serverTags,
                $position,
                self::CRATE_NEW_KEYS,
                self::CRATE_OLD_KEYS
            );
        }
    }

    /**
     * @return array{id: int|string, starts_at: string}
     */
    private function findSeason(): array
    {
        $season = $this->db->createCommand(
            'SELECT id, starts_at FROM battle_pass_season WHERE slug = :slug',
            [':slug' => self::SEASON_SLUG]
        )->queryOne();

        if (!$season) {
            throw new RuntimeException('Battle Pass Season 1 not found.');
        }

        return $season;
    }

    /**
     * @return string[]
     */
    private function findTrackedServerTags(): array
    {
        $serverTags = (new Query())
            ->select('tag')
            ->from('servers')
            ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
            ->orderBy(['sort' => SORT_ASC])
            ->column($this->db);

        $serverTags = array_values(array_unique(array_filter(array_map('trim', $serverTags))));
        if ($serverTags === []) {
            throw new RuntimeException('No active servers found for Battle Pass statistics.');
        }

        return $serverTags;
    }

    /**
     * @param string[] $serverTags
     * @param string[] $expectedOldKeys
     * @param string[] $newKeys
     */
    private function changeTaskKeys(
        int $seasonId,
        string $seasonStartsAt,
        array $serverTags,
        int $position,
        array $expectedOldKeys,
        array $newKeys
    ): void {
        $task = $this->db->createCommand(
            'SELECT id, check_type, check_params, max_progress
             FROM tasks_v2
             WHERE battle_pass_season_id = :seasonId
               AND battle_pass_position = :position
               AND type = :type',
            [
                ':seasonId' => $seasonId,
                ':position' => $position,
                ':type' => TaskV2::TYPE_BATTLE_PASS,
            ]
        )->queryOne();

        if (!$task) {
            throw new RuntimeException("Battle Pass task #{$position} not found.");
        }
        if ($task['check_type'] !== TaskV2::CHECK_TYPE_STATISTICS_PARAM) {
            throw new RuntimeException("Battle Pass task #{$position} is not a statistics task.");
        }

        $params = $this->decodeParams($task['check_params'], $position);

        $currentKeys = $this->normalizeKeys((string)($params['stat_key'] ?? ''));
        if ($currentKeys === $newKeys) {
            return;
        }
        if ($currentKeys !== $expectedOldKeys) {
            throw new RuntimeException(
                "Battle Pass task #{$position} has unexpected statistic keys: " . implode(',', $currentKeys)
            );
        }

        $this->rebaseUnlockedTasks(
            $seasonId,
            (int)$task['id'],
            substr($seasonStartsAt, 0, 10),
            $serverTags,
            $expectedOldKeys,
            $newKeys
        );

        $params['stat_key'] = implode(',', $newKeys);
        $requiredValue = (int)($params['required_value'] ?? 0);
        if ($requiredValue <= 0) {
            throw new RuntimeException("Battle Pass task #{$position} has invalid required_value.");
        }

        $this->update('tasks_v2', [
            'check_params' => Json::encode($params),
            'max_progress' => $requiredValue,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => (int)$task['id']]);
    }

    /**
     * @param string[] $serverTags
     * @param string[] $oldKeys
     * @param string[] $newKeys
     */
    private function rebaseUnlockedTasks(
        int $seasonId,
        int $taskId,
        string $seasonStartDate,
        array $serverTags,
        array $oldKeys,
        array $newKeys
    ): void {
        $states = $this->db->createCommand(
            'SELECT state.id, state.baseline_value, user.steam_id
             FROM battle_pass_user_task state
             INNER JOIN user ON user.id = state.user_id
             LEFT JOIN task_v2_user_completion completion
               ON completion.user_id = state.user_id
              AND completion.task_id = state.task_id
             WHERE state.season_id = :seasonId
               AND state.task_id = :taskId
               AND COALESCE(completion.count_completed, 0) = 0',
            [
                ':seasonId' => $seasonId,
                ':taskId' => $taskId,
            ]
        )->queryAll();

        foreach ($states as $state) {
            $oldCurrent = $this->getStatisticsSum(
                (string)$state['steam_id'],
                $serverTags,
                $oldKeys,
                $seasonStartDate
            );
            $newCurrent = $this->getStatisticsSum(
                (string)$state['steam_id'],
                $serverTags,
                $newKeys,
                $seasonStartDate
            );
            $oldProgress = max(0, $oldCurrent - (int)$state['baseline_value']);
            $newBaseline = max(0, $newCurrent - $oldProgress);

            $this->update('battle_pass_user_task', [
                'baseline_value' => $newBaseline,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int)$state['id']]);
        }
    }

    /**
     * @param string[] $serverTags
     * @param string[] $statKeys
     */
    private function getStatisticsSum(
        string $steamId,
        array $serverTags,
        array $statKeys,
        string $startDate
    ): int {
        $buildQuery = static function (string $tableName) use ($steamId, $serverTags, $statKeys, $startDate): Query {
            return (new Query())
                ->select('value')
                ->from($tableName)
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['server_tag' => $serverTags])
                ->andWhere(['key' => $statKeys])
                ->andWhere("SUBSTRING_INDEX(wipe, '/', -1) >= :startDate", [':startDate' => $startDate]);
        };

        $combined = $buildQuery('statistics')
            ->union($buildQuery('statistics_archive'), true);

        return (int)(new Query())
            ->from(['battle_pass_statistics' => $combined])
            ->sum('CAST(value AS SIGNED)', $this->db);
    }

    /**
     * @return string[]
     */
    private function normalizeKeys(string $keys): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $keys))));
    }

    /**
     * Older environments contain values that were JSON-encoded twice. Decode
     * a bounded number of layers so both the legacy and normalized formats are
     * accepted without hiding genuinely invalid task configuration.
     *
     * @param mixed $value
     */
    private function decodeParams($value, int $position): array
    {
        $params = $value;

        for ($depth = 0; $depth < 3 && is_string($params); $depth++) {
            if (trim($params) === '') {
                break;
            }

            try {
                $params = Json::decode($params, true);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Battle Pass task #{$position} has invalid check_params.",
                    0,
                    $exception
                );
            }
        }

        if (!is_array($params)) {
            throw new RuntimeException("Battle Pass task #{$position} has invalid check_params.");
        }

        return $params;
    }
}
