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
        $requiredValue = (int)($params['required_value'] ?? 0);
        $serverId = (int)($params['server_id'] ?? 0);
        $sumAllServers = !empty($params['sum_all_servers']) && $params['sum_all_servers'] === true;

        if ($requiredValue <= 0) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указано требуемое значение.')
            );
        }

        $currentValue = 0;

        if ($sumAllServers) {
            // Суммируем статистику по всем серверам
            $servers = Servers::find()->where(['is_active' => 1])->all();
            foreach ($servers as $server) {
                $wipe = $server->currentWipe();
                $playerStats = Statistics::getPlayerStats($server, $user->steam_id, $wipe);
                $currentValue += Statistics::getParam($playerStats, $statKey);
            }
        } else {
            // Статистика по конкретному серверу
            if ($serverId > 0) {
                $server = Servers::findOne($serverId);
                if (!$server) {
                    return CheckResult::failure(
                        Yii::t('common', 'Сервер не найден.')
                    );
                }
            } else {
                // Используем текущий сервер пользователя
                $server = $user->server ?? Servers::find()->where(['is_active' => 1])->orderBy(['sort' => SORT_ASC])->one();
                if (!$server) {
                    return CheckResult::failure(
                        Yii::t('common', 'Не удалось определить сервер.')
                    );
                }
            }

            $wipe = $server->currentWipe();
            $playerStats = Statistics::getPlayerStats($server, $user->steam_id, $wipe);
            $currentValue = Statistics::getParam($playerStats, $statKey);
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
}


















