<?php

namespace console\controllers;

use common\models\servers\Servers;
use Yii;
use yii\console\Controller;

/**
 * Контроллер для работы с метриками серверов (ИТОГИ ГОДА)
 */
class ServerYearReviewController extends Controller
{
    /**
     * Обновление кэша метрик для всех серверов
     * Использование: php yii server-year-review/update-metrics [serverId]
     * 
     * @param int|null $serverId ID сервера (если не указан, обновляются все активные серверы)
     * @return int
     */
    public function actionUpdateMetrics($serverId = null)
    {
        $this->stdout("Начинаем обновление кэша метрик серверов...\n", \yii\helpers\Console::FG_GREEN);
        
        $servers = [];
        
        if ($serverId) {
            $server = Servers::findOne($serverId);
            if (!$server) {
                $this->stderr("Сервер с ID {$serverId} не найден!\n", \yii\helpers\Console::FG_RED);
                return 1;
            }
            $servers[] = $server;
        } else {
            // Получаем все активные серверы
            $servers = Servers::find()
                ->where(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                ->orderBy(['sort' => SORT_ASC])
                ->all();
        }
        
        if (empty($servers)) {
            $this->stderr("Серверы для обновления не найдены!\n", \yii\helpers\Console::FG_YELLOW);
            return 0;
        }
        
        $totalUpdated = 0;
        $totalServers = count($servers);
        
        foreach ($servers as $server) {
            $this->stdout("Обновление метрик для сервера: {$server->id} - " . Yii::t('database', $server->name) . "... ", \yii\helpers\Console::FG_CYAN);
            
            try {
                $count = $server->refreshMetricsCache();
                $totalUpdated += $count;
                $this->stdout("OK ({$count} метрик)\n", \yii\helpers\Console::FG_GREEN);
            } catch (\Exception $e) {
                $this->stderr("ОШИБКА: {$e->getMessage()}\n", \yii\helpers\Console::FG_RED);
            }
        }
        
        $this->stdout("\nОбновление завершено!\n", \yii\helpers\Console::FG_GREEN);
        $this->stdout("Серверов обработано: {$totalServers}\n", \yii\helpers\Console::FG_CYAN);
        $this->stdout("Всего метрик обновлено: {$totalUpdated}\n", \yii\helpers\Console::FG_CYAN);
        
        return 0;
    }
}

