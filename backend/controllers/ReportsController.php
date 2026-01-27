<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\invoice\Invoice;
use common\models\skindrops\Skindrops;
use yii\web\Controller;
use Yii;

class ReportsController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        // Кэшируем результаты на 1 час
        $cacheKey = 'reports_index_data_v4';
        $data = Yii::$app->cache->get($cacheKey);
        
        if ($data === false) {
            ini_set('memory_limit', '512M');
            set_time_limit(300); // 5 минут максимум
            
            // Получаем серверы с кэшированием
            $serversCacheKey = 'reports_servers_list';
            $servers = Yii::$app->cache->get($serversCacheKey);
            if ($servers === false) {
                $servers = \common\models\servers\Servers::find()
                    ->select(['id', 'name', 'tag', 'wipe', 'next_wipe'])
                    ->orderBy(['sort' => SORT_ASC])
                    ->asArray()
                    ->all();
                Yii::$app->cache->set($serversCacheKey, $servers, 3600);
            }
            
            if (empty($servers)) {
                $data = [
                    'users' => [],
                    'serverStats' => [],
                    'totalReports' => 0,
                    'totalUsers' => 0,
                ];
                Yii::$app->cache->set($cacheKey, $data, 3600);
                return $this->render('index', $data);
            }
            
            // Строим массивы для SQL запросов
            $wipeDates = [];
            $serverTags = [];
            $serversByWipe = [];
            foreach ($servers as $server) {
                $wipeDate = (new \DateTime($server['wipe']))->format('Y-m-d') . "/" . (new \DateTime($server['next_wipe']))->format('Y-m-d');
                $wipeDates[$wipeDate] = true;
                $serverTags[$server['tag']] = true;
                if (!isset($serversByWipe[$wipeDate])) {
                    $serversByWipe[$wipeDate] = [];
                }
                $serversByWipe[$wipeDate][$server['tag']] = $server;
            }
            
            $wipeDatesList = array_keys($wipeDates);
            $serverTagsList = array_keys($serverTags);
            
            // Кэшируем проверки пользователей отдельно
            $checkingsCacheKey = 'reports_user_checkings';
            $allUserCheckings = Yii::$app->cache->get($checkingsCacheKey);
            if ($allUserCheckings === false) {
                $userCheckings = \Yii::$app->db->createCommand("
                    SELECT 
                        u.steam_id,
                        MAX(uc.created_at) as checking_at
                    FROM user_checking uc
                    INNER JOIN user u ON u.id = uc.user_id
                    GROUP BY u.steam_id
                ")->queryAll();
                
                $allUserCheckings = [];
                foreach ($userCheckings as $checking) {
                    $allUserCheckings[$checking['steam_id']] = $checking['checking_at'];
                }
                Yii::$app->cache->set($checkingsCacheKey, $allUserCheckings, 1800);
            }
            
            // Используем подготовленные параметры для безопасности
            $params = [':status' => \common\models\user\User::STATUS_ACTIVE];
            $placeholdersWipe = [];
            $placeholdersTags = [];
            
            foreach ($wipeDatesList as $i => $wipeDate) {
                $paramName = ':wipe' . $i;
                $placeholdersWipe[] = $paramName;
                $params[$paramName] = $wipeDate;
            }
            
            foreach ($serverTagsList as $i => $tag) {
                $paramName = ':tag' . $i;
                $placeholdersTags[] = $paramName;
                $params[$paramName] = $tag;
            }
            
            $placeholdersWipeStr = implode(',', $placeholdersWipe);
            $placeholdersTagsStr = implode(',', $placeholdersTags);
            
            // Оптимизированный запрос с использованием индексов
            // Убираем лишние поля из GROUP BY для ускорения
            $reportsData = \Yii::$app->db->createCommand("
                SELECT 
                    r.recepient_steam_id,
                    r.server_tag,
                    r.wipe,
                    MIN(r.created_at) as first_report,
                    COUNT(*) as reports_count,
                    u.id as user_id,
                    u.steam_id,
                    u.username
                FROM servers_reports r
                INNER JOIN user u ON u.steam_id = r.recepient_steam_id 
                    AND u.status = :status 
                    AND u.unbanned_at IS NULL
                WHERE r.wipe IN ({$placeholdersWipeStr})
                AND r.server_tag IN ({$placeholdersTagsStr})
                GROUP BY r.recepient_steam_id, r.server_tag, r.wipe, u.id, u.steam_id, u.username
            ")
                ->bindValues($params)
                ->queryAll();
            
            $users = [];
            $serverStatsData = [];
            $totalReports = 0;
            $totalUsers = 0;
            
            // Инициализируем статистику по серверам
            foreach ($servers as $server) {
                $serverStatsData[$server['tag']] = [
                    'server' => $server,
                    'reports_count' => 0,
                    'users_count' => 0,
                    'users_set' => [],
                ];
            }
            
            // Обрабатываем данные из SQL запроса
            foreach ($reportsData as $report) {
                $wipeDate = $report['wipe'];
                $serverTag = $report['server_tag'];
                $steamId = $report['recepient_steam_id'];
                
                // Проверяем, существует ли такой сервер с такой wipe датой
                if (!isset($serversByWipe[$wipeDate][$serverTag])) {
                    continue;
                }
                
                // Проверяем, был ли пользователь проверен после первого репорта
                if (!empty($allUserCheckings[$steamId]) && 
                    $report['first_report'] < $allUserCheckings[$steamId]) {
                    continue;
                }
                
                $reportsCount = (int)$report['reports_count'];
                $totalReports += $reportsCount;
                
                // Обновляем статистику по пользователю
                if (empty($users[$steamId])) {
                    $users[$steamId] = [
                        'count' => $reportsCount,
                        'servers' => [$serverTag],
                        'steam_id' => $report['steam_id'],
                        'username' => $report['username'],
                        'user_id' => $report['user_id'],
                    ];
                    $totalUsers++;
                } else {
                    $users[$steamId]['count'] += $reportsCount;
                    if (!in_array($serverTag, $users[$steamId]['servers'])) {
                        $users[$steamId]['servers'][] = $serverTag;
                    }
                }
                
                // Обновляем статистику по серверу
                if (isset($serverStatsData[$serverTag])) {
                    $serverStatsData[$serverTag]['reports_count'] += $reportsCount;
                    if (!isset($serverStatsData[$serverTag]['users_set'][$steamId])) {
                        $serverStatsData[$serverTag]['users_set'][$steamId] = true;
                        $serverStatsData[$serverTag]['users_count']++;
                    }
                }
            }
            
            // Формируем финальную статистику по серверам
            $serverStats = [];
            foreach ($serverStatsData as $stat) {
                unset($stat['users_set']);
                $serverStats[] = $stat;
            }
            
            $data = [
                'users' => $users,
                'serverStats' => $serverStats,
                'totalReports' => $totalReports,
                'totalUsers' => $totalUsers,
            ];
            
            Yii::$app->cache->set($cacheKey, $data, 3600); // Кэш на 1 час
        }
        
        return $this->render('index', $data);
    }

}