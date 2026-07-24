<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Json;

/**
 * Контроллер для синхронизации скинов с rust.tm в таблицу market_skins
 */
class MarketSkinsController extends Controller
{
    /**
     * Синхронизация скинов с rust.tm API
     * Использование: php yii market-skins/sync
     * 
     * @return int
     */
    public function actionSync()
    {
        if (!Yii::$app->settings->get('section_market')) {
            $this->stdout("Маркет скинов отключён, синхронизация пропущена.\n");
            return 0;
        }

        $this->stdout("Начинаем синхронизацию скинов с rust.tm...\n", \yii\helpers\Console::FG_GREEN);
        
        try {
            // Вызываем API endpoint синхронизации
            $url = Yii::$app->params['frontendUrl'] ?? 'http://localhost:3000';
            $apiUrl = $url . '/api/market/skins/sync';
            
            $this->stdout("Вызываем API: {$apiUrl}\n", \yii\helpers\Console::FG_CYAN);
            
            // Используем curl для вызова API
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 минут таймаут
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("CURL Error: {$error}");
            }
            
            if ($httpCode !== 200) {
                throw new \Exception("HTTP Error: {$httpCode}");
            }
            
            $result = Json::decode($response);
            
            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Unknown error');
            }
            
            $stats = $result['stats'] ?? [];
            $byGame = $result['byGame'] ?? [];
            
            $this->stdout("✓ Синхронизация завершена успешно!\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("\nОбщая статистика:\n", \yii\helpers\Console::FG_CYAN);
            $this->stdout("Всего элементов: {$stats['total']}\n", \yii\helpers\Console::FG_CYAN);
            $this->stdout("Добавлено: {$stats['inserted']}\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("Обновлено: {$stats['updated']}\n", \yii\helpers\Console::FG_YELLOW);
            $this->stdout("Пропущено (дубликаты с более высокой ценой): {$stats['skipped']}\n", \yii\helpers\Console::FG_CYAN);
            
            if (!empty($byGame)) {
                $this->stdout("\nПо играм:\n", \yii\helpers\Console::FG_CYAN);
                
                if (!empty($byGame['rust'])) {
                    $rust = $byGame['rust'];
                    $this->stdout("  Rust:\n", \yii\helpers\Console::FG_YELLOW);
                    $this->stdout("    Всего: {$rust['total']}\n");
                    $this->stdout("    Добавлено: {$rust['inserted']}\n", \yii\helpers\Console::FG_GREEN);
                    $this->stdout("    Обновлено: {$rust['updated']}\n", \yii\helpers\Console::FG_YELLOW);
                    $this->stdout("    Пропущено: {$rust['skipped']}\n");
                }
                
                if (!empty($byGame['cs2'])) {
                    $cs2 = $byGame['cs2'];
                    $this->stdout("  CS2:\n", \yii\helpers\Console::FG_YELLOW);
                    $this->stdout("    Всего: {$cs2['total']}\n");
                    $this->stdout("    Добавлено: {$cs2['inserted']}\n", \yii\helpers\Console::FG_GREEN);
                    $this->stdout("    Обновлено: {$cs2['updated']}\n", \yii\helpers\Console::FG_YELLOW);
                    $this->stdout("    Пропущено: {$cs2['skipped']}\n");
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->stderr("✗ Ошибка синхронизации: {$e->getMessage()}\n", \yii\helpers\Console::FG_RED);
            Yii::error("Market skins sync error: {$e->getMessage()}", __METHOD__);
            return 1;
        }
    }
}

