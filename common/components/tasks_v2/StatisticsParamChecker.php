<?php

namespace common\components\tasks_v2;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;
use yii\helpers\Json;

/**
 * Проверка параметров статистики игрока
 */
class StatisticsParamChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = is_array($task->check_params) ? $task->check_params : Json::decode($task->check_params, true);
        
        if (empty($params['stat_key'])) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указан параметр статистики.')
            );
        }

        $statKey = $params['stat_key'];
        // Поддержка нескольких ключей через запятую
        $statKeys = array_map('trim', explode(',', $statKey));
        
        $requiredValue = (int)($params['required_value'] ?? 0);
        $serverId = (int)($params['server_id'] ?? 0);
        $sumAllServers = !empty($params['sum_all_servers']) && $params['sum_all_servers'] === true;

        if ($requiredValue <= 0) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указано требуемое значение.')
            );
        }

        // Дата начала отсчета: 04.12.2025 22:00
        $startDate = '2025-12-04';
        
        $currentValue = 0;

        if ($sumAllServers) {
            // Суммируем статистику по всем серверам начиная с указанной даты (не закрытые — колонки is_active в servers нет)
            $servers = Servers::find()
                ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
                ->orderBy(['sort' => SORT_ASC])
                ->all();
            foreach ($servers as $server) {
                $playerStats = $this->getPlayerStatsSinceDate($server, $user->steam_id, $startDate);
                $currentValue += $this->getStatsSum($playerStats, $statKeys);
            }
        } else {
            // Статистика по конкретному серверу начиная с указанной даты
            if ($serverId > 0) {
                $server = Servers::findOne($serverId);
                if (!$server) {
                    return CheckResult::failure(
                        Yii::t('common', 'Сервер не найден.')
                    );
                }
            } else {
                // Используем текущий сервер пользователя
                $server = $user->server ?? Servers::find()
                    ->andWhere(['NOT IN', 'status', [Servers::STATUS_CLOSED]])
                    ->orderBy(['sort' => SORT_ASC])
                    ->one();
                if (!$server) {
                    return CheckResult::failure(
                        Yii::t('common', 'Не удалось определить сервер.')
                    );
                }
            }

            $playerStats = $this->getPlayerStatsSinceDate($server, $user->steam_id, $startDate);
            $currentValue = $this->getStatsSum($playerStats, $statKeys);
        }

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

        $remaining = $requiredValue - $currentValue;
        return CheckResult::failure(
            Yii::t('common', 'Прогресс: {current} из {required}. Осталось: {remaining}', [
                'current' => number_format($currentValue, 0, '.', ' '),
                'required' => number_format($requiredValue, 0, '.', ' '),
                'remaining' => number_format($remaining, 0, '.', ' '),
            ]),
            $currentValue,
            $requiredValue
        );
    }

    /**
     * Получить статистику игрока начиная с указанной даты
     * Суммирует значения по всем вайпам, начиная с указанной даты
     * 
     * @param Servers $server
     * @param string $steamId
     * @param string $startDate Дата в формате Y-m-d
     * @return array Массив статистики с ключами и суммированными значениями
     */
    private function getPlayerStatsSinceDate(Servers $server, string $steamId, string $startDate): array
    {
        // Получаем все записи статистики для игрока на сервере, где начало вайпа >= указанной даты
        // Поле wipe хранится в формате "Y-m-d/Y-m-d", извлекаем первую часть (дату начала вайпа)
        $statistics = Statistics::find()
            ->select(['key', 'SUM(value) as value'])
            ->andWhere(['steam_id' => $steamId])
            ->andWhere(['server_tag' => $server->tag])
            ->andWhere("SUBSTRING_INDEX(wipe, '/', 1) >= :startDate", [':startDate' => $startDate])
            ->groupBy('key')
            ->indexBy('key')
            ->asArray()
            ->all();

        // Преобразуем результат: SUM возвращает строку, нужно преобразовать в int
        $result = [];
        foreach ($statistics as $key => $item) {
            $result[$key] = (int)$item['value'];
        }

        return $result;
    }

    /**
     * Получить сумму значений статистики по указанным ключам
     * 
     * @param array $playerStats Массив статистики игрока
     * @param array $statKeys Массив ключей статистики
     * @return int Сумма значений по всем указанным ключам
     */
    private function getStatsSum(array $playerStats, array $statKeys): int
    {
        $sum = 0;
        foreach ($statKeys as $key) {
            $sum += Statistics::getParam($playerStats, trim($key));
        }
        return $sum;
    }
}























