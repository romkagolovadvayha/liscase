<?php

namespace common\components\tasks_v2;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\statistics\StatisticsArchive;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;
use yii\db\Query;
use yii\helpers\Json;

/**
 * Проверка накопительных параметров статистики игрока.
 */
class StatisticsParamChecker implements TaskCheckerInterface
{
    public function check(TaskV2 $task, User $user): CheckResult
    {
        return $this->checkFromBaseline($task, $user, 0, '2025-12-04');
    }

    /**
     * Проверяет задание относительно персонального снимка статистики.
     */
    public function checkFromBaseline(TaskV2 $task, User $user, int $baselineValue, ?string $startDate = null): CheckResult
    {
        $params = $this->getParams($task);
        $requiredValue = (int)($params['required_value'] ?? 0);
        if (empty($params['stat_key'])) {
            return CheckResult::failure(Yii::t('common', 'Настройки задания неверны: не указан параметр статистики.'));
        }
        if ($requiredValue <= 0) {
            return CheckResult::failure(Yii::t('common', 'Настройки задания неверны: не указано требуемое значение.'));
        }

        try {
            $rawValue = $this->getCurrentValue($task, $user, $startDate);
        } catch (\RuntimeException $e) {
            return CheckResult::failure($e->getMessage());
        }

        $currentValue = max(0, $rawValue - max(0, $baselineValue));
        if ($currentValue >= $requiredValue) {
            return CheckResult::success(
                Yii::t('common', 'Задание выполнено! Текущее значение: {current} из {required}', [
                    'current' => number_format($currentValue, 0, '.', ' '),
                    'required' => number_format($requiredValue, 0, '.', ' '),
                ]),
                $currentValue,
                $requiredValue
            );
        }

        return CheckResult::failure(
            Yii::t('common', 'Прогресс: {current} из {required}. Осталось: {remaining}', [
                'current' => number_format($currentValue, 0, '.', ' '),
                'required' => number_format($requiredValue, 0, '.', ' '),
                'remaining' => number_format($requiredValue - $currentValue, 0, '.', ' '),
            ]),
            $currentValue,
            $requiredValue
        );
    }

    /**
     * Возвращает текущее абсолютное значение, которое можно сохранить как снимок.
     */
    public function getCurrentValue(TaskV2 $task, User $user, ?string $startDate = null): int
    {
        $params = $this->getParams($task);
        if (empty($params['stat_key'])) {
            throw new \RuntimeException(Yii::t('common', 'Настройки задания неверны: не указан параметр статистики.'));
        }

        $statKeys = array_values(array_filter(array_map('trim', explode(',', (string)$params['stat_key']))));
        $serverId = (int)($params['server_id'] ?? 0);
        $sumAllServers = !empty($params['sum_all_servers']);
        $startDate = $startDate ?: '2025-12-04';

        if ($sumAllServers) {
            $serverTags = Servers::find()
                ->select('tag')
                ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
                ->orderBy(['sort' => SORT_ASC])
                ->column();

            return $this->getStatsSum(
                $this->getPlayerStatsSinceDate($serverTags, $user->steam_id, $startDate),
                $statKeys
            );
        }

        if ($serverId > 0) {
            $server = Servers::findOne($serverId);
        } else {
            $server = $user->server ?? Servers::find()
                ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
                ->orderBy(['sort' => SORT_ASC])
                ->one();
        }
        if (!$server) {
            throw new \RuntimeException(Yii::t('common', 'Не удалось определить сервер.'));
        }

        return $this->getStatsSum(
            $this->getPlayerStatsSinceDate([$server->tag], $user->steam_id, $startDate),
            $statKeys
        );
    }

    private function getParams(TaskV2 $task): array
    {
        if (is_array($task->check_params)) {
            return $task->check_params;
        }
        if (!$task->check_params) {
            return [];
        }
        $decoded = Json::decode($task->check_params, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns cumulative statistics from every wipe that overlaps the tracked
     * period. A wipe that started before the Battle Pass still has to be
     * included: the personal baseline removes everything earned before the
     * task was unlocked. Finished wipes live in statistics_archive, so both
     * tables must participate to keep progress from dropping after a wipe.
     *
     * @param string[] $serverTags
     */
    private function getPlayerStatsSinceDate(array $serverTags, string $steamId, string $startDate): array
    {
        $serverTags = array_values(array_unique(array_filter(array_map('trim', $serverTags))));
        if ($serverTags === []) {
            return [];
        }

        $buildSourceQuery = static function (string $tableName) use ($steamId, $serverTags, $startDate): Query {
            return (new Query())
                ->select(['key', 'value'])
                ->from($tableName)
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['server_tag' => $serverTags])
                ->andWhere("SUBSTRING_INDEX(wipe, '/', -1) >= :startDate", [':startDate' => $startDate]);
        };

        $combined = $buildSourceQuery(Statistics::tableName())
            ->union($buildSourceQuery(StatisticsArchive::tableName()), true);

        $statistics = (new Query())
            ->select(['key', 'SUM(CAST(value AS SIGNED)) as value'])
            ->from(['battle_pass_statistics' => $combined])
            ->groupBy('key')
            ->indexBy('key')
            ->all(Statistics::getDb());

        $result = [];
        foreach ($statistics as $key => $item) {
            $result[$key] = (int)$item['value'];
        }
        return $result;
    }

    private function getStatsSum(array $playerStats, array $statKeys): int
    {
        $sum = 0;
        foreach ($statKeys as $key) {
            $sum += Statistics::getParam($playerStats, trim($key));
        }
        return $sum;
    }
}
