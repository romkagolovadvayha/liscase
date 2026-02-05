<?php

namespace api\controllers\v1;

use Yii;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use common\models\servers\Servers;
use common\models\servers\ServersStatisticsHistory;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с историей статистики серверов
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Servers Statistics History")
 */
class ServersStatisticsHistoryController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // Все методы публичные, JWT не требуется
        return $behaviors;
    }

    /**
     * Получить статистику за месяц (максимальные значения по дням)
     * 
     * @OA\Get(
     *     path="/v1/servers-statistics-history/month",
     *     operationId="getServersStatisticsMonth",
     *     tags={"Servers Statistics History"},
     *     summary="Получить статистику за месяц (максимальные значения по дням)",
     *     description="Публичный метод, авторизация не требуется. Возвращает максимальные значения players, joined, queued по дням за последний месяц",
     *     @OA\Parameter(
     *         name="server_id",
     *         in="query",
     *         required=true,
     *         description="ID сервера",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статистика за месяц",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="date", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="players", type="integer", example=150),
     *                     @OA\Property(property="joined", type="integer", example=10),
     *                     @OA\Property(property="queued", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры"),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionMonth()
    {
        $serverId = Yii::$app->request->get('server_id');
        if (!$serverId) {
            throw new BadRequestHttpException('Параметр server_id обязателен');
        }

        $server = Servers::findOne($serverId);
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        // Получаем данные за последние 30 дней
        $date30DaysAgo = date('Y-m-d 00:00:00', strtotime('-30 days'));
        
        $records = ServersStatisticsHistory::find()
            ->where(['server_id' => $serverId])
            ->andWhere(['>=', 'created_at', $date30DaysAgo])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        // Группируем по дням и находим максимальные значения
        $groupedByDay = [];
        foreach ($records as $record) {
            $day = date('Y-m-d', strtotime($record->created_at));
            
            if (!isset($groupedByDay[$day])) {
                $groupedByDay[$day] = [
                    'date' => $day,
                    'players' => $record->players,
                    'joined' => $record->joined,
                    'queued' => $record->queued,
                ];
            } else {
                // Обновляем только если значения больше
                if ($record->players > $groupedByDay[$day]['players']) {
                    $groupedByDay[$day]['players'] = $record->players;
                }
                if ($record->joined > $groupedByDay[$day]['joined']) {
                    $groupedByDay[$day]['joined'] = $record->joined;
                }
                if ($record->queued > $groupedByDay[$day]['queued']) {
                    $groupedByDay[$day]['queued'] = $record->queued;
                }
            }
        }

        // Преобразуем в массив и сортируем по дате
        $result = array_values($groupedByDay);
        usort($result, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $this->successResponse($result);
    }

    /**
     * Получить статистику за неделю (максимальные значения по дням)
     * 
     * @OA\Get(
     *     path="/v1/servers-statistics-history/week",
     *     operationId="getServersStatisticsWeek",
     *     tags={"Servers Statistics History"},
     *     summary="Получить статистику за неделю (максимальные значения по дням)",
     *     description="Публичный метод, авторизация не требуется. Возвращает максимальные значения players, joined, queued по дням за последнюю неделю",
     *     @OA\Parameter(
     *         name="server_id",
     *         in="query",
     *         required=true,
     *         description="ID сервера",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статистика за неделю",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="date", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="players", type="integer", example=150),
     *                     @OA\Property(property="joined", type="integer", example=10),
     *                     @OA\Property(property="queued", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры"),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionWeek()
    {
        $serverId = Yii::$app->request->get('server_id');
        if (!$serverId) {
            throw new BadRequestHttpException('Параметр server_id обязателен');
        }

        $server = Servers::findOne($serverId);
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        // Получаем данные за последние 7 дней
        $date7DaysAgo = date('Y-m-d 00:00:00', strtotime('-7 days'));
        
        $records = ServersStatisticsHistory::find()
            ->where(['server_id' => $serverId])
            ->andWhere(['>=', 'created_at', $date7DaysAgo])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        // Группируем по дням и находим максимальные значения
        $groupedByDay = [];
        foreach ($records as $record) {
            $day = date('Y-m-d', strtotime($record->created_at));
            
            if (!isset($groupedByDay[$day])) {
                $groupedByDay[$day] = [
                    'date' => $day,
                    'players' => $record->players,
                    'joined' => $record->joined,
                    'queued' => $record->queued,
                ];
            } else {
                // Обновляем только если значения больше
                if ($record->players > $groupedByDay[$day]['players']) {
                    $groupedByDay[$day]['players'] = $record->players;
                }
                if ($record->joined > $groupedByDay[$day]['joined']) {
                    $groupedByDay[$day]['joined'] = $record->joined;
                }
                if ($record->queued > $groupedByDay[$day]['queued']) {
                    $groupedByDay[$day]['queued'] = $record->queued;
                }
            }
        }

        // Преобразуем в массив и сортируем по дате
        $result = array_values($groupedByDay);
        usort($result, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $this->successResponse($result);
    }

    /**
     * Получить статистику за день (максимальные значения по часам)
     * 
     * @OA\Get(
     *     path="/v1/servers-statistics-history/day",
     *     operationId="getServersStatisticsDay",
     *     tags={"Servers Statistics History"},
     *     summary="Получить статистику за день (максимальные значения по часам)",
     *     description="Публичный метод, авторизация не требуется. Возвращает максимальные значения players, joined, queued по часам. Если date не указан, возвращает данные за последние 24 часа. Если date указан, возвращает данные за указанный день",
     *     @OA\Parameter(
     *         name="server_id",
     *         in="query",
     *         required=true,
     *         description="ID сервера",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=false,
     *         description="Дата (формат: Y-m-d). Если не указана, возвращаются данные за последние 24 часа",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статистика за день",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="hour", type="integer", example=14, description="Час (0-23)"),
     *                     @OA\Property(property="players", type="integer", example=150),
     *                     @OA\Property(property="joined", type="integer", example=10),
     *                     @OA\Property(property="queued", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Неверные параметры"),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionDay()
    {
        $serverId = Yii::$app->request->get('server_id');
        if (!$serverId) {
            throw new BadRequestHttpException('Параметр server_id обязателен');
        }

        $server = Servers::findOne($serverId);
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $date = Yii::$app->request->get('date');
        
        // Если дата не указана, используем последние 24 часа начиная с того же часа вчера
        if ($date === null) {
            // Получаем текущий час (0-23)
            $currentHour = (int)date('H');
            
            // Время начала: вчера в этот же час (00 минут, 00 секунд)
            $timeStart = date('Y-m-d', strtotime('-1 day')) . ' ' . str_pad($currentHour, 2, '0', STR_PAD_LEFT) . ':00:00';
            
            // Текущее время
            $now = date('Y-m-d H:i:s');
            
            $records = ServersStatisticsHistory::find()
                ->where(['server_id' => $serverId])
                ->andWhere(['>=', 'created_at', $timeStart])
                ->andWhere(['<=', 'created_at', $now])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();
            
            // Группируем по дате+часу и находим максимальные значения
            // Ключ: "Y-m-d H" для правильной группировки часов из разных дней
            $groupedByDateTime = [];
            foreach ($records as $record) {
                $recordTimestamp = strtotime($record->created_at);
                $dateTimeKey = date('Y-m-d H', $recordTimestamp);
                $hour = (int)date('H', $recordTimestamp);
                
                if (!isset($groupedByDateTime[$dateTimeKey])) {
                    $groupedByDateTime[$dateTimeKey] = [
                        'date' => date('Y-m-d', $recordTimestamp),
                        'hour' => $hour,
                        'players' => $record->players,
                        'joined' => $record->joined,
                        'queued' => $record->queued,
                        '_sort_key' => $dateTimeKey, // Для сортировки
                    ];
                } else {
                    // Обновляем только если значения больше
                    if ($record->players > $groupedByDateTime[$dateTimeKey]['players']) {
                        $groupedByDateTime[$dateTimeKey]['players'] = $record->players;
                    }
                    if ($record->joined > $groupedByDateTime[$dateTimeKey]['joined']) {
                        $groupedByDateTime[$dateTimeKey]['joined'] = $record->joined;
                    }
                    if ($record->queued > $groupedByDateTime[$dateTimeKey]['queued']) {
                        $groupedByDateTime[$dateTimeKey]['queued'] = $record->queued;
                    }
                }
            }

            // Преобразуем в массив и сортируем по дате и времени
            $result = array_values($groupedByDateTime);
            usort($result, function($a, $b) {
                return strcmp($a['_sort_key'], $b['_sort_key']);
            });
            
            // Удаляем служебное поле _sort_key
            foreach ($result as &$item) {
                unset($item['_sort_key']);
            }
            
            // Убираем первое значение (первый час)
            if (!empty($result)) {
                array_shift($result);
            }
        } else {
            // Валидация формата даты
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new BadRequestHttpException('Неверный формат даты. Используйте формат Y-m-d (например, 2024-01-01)');
            }

            // Получаем данные за указанный день
            $dayStart = $date . ' 00:00:00';
            $dayEnd = $date . ' 23:59:59';
            
            $records = ServersStatisticsHistory::find()
                ->where(['server_id' => $serverId])
                ->andWhere(['>=', 'created_at', $dayStart])
                ->andWhere(['<=', 'created_at', $dayEnd])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            // Группируем по часам и находим максимальные значения
            $groupedByHour = [];
            foreach ($records as $record) {
                $hour = (int)date('H', strtotime($record->created_at));
                
                if (!isset($groupedByHour[$hour])) {
                    $groupedByHour[$hour] = [
                        'hour' => $hour,
                        'players' => $record->players,
                        'joined' => $record->joined,
                        'queued' => $record->queued,
                    ];
                } else {
                    // Обновляем только если значения больше
                    if ($record->players > $groupedByHour[$hour]['players']) {
                        $groupedByHour[$hour]['players'] = $record->players;
                    }
                    if ($record->joined > $groupedByHour[$hour]['joined']) {
                        $groupedByHour[$hour]['joined'] = $record->joined;
                    }
                    if ($record->queued > $groupedByHour[$hour]['queued']) {
                        $groupedByHour[$hour]['queued'] = $record->queued;
                    }
                }
            }

            // Преобразуем в массив и сортируем по часу
            $result = array_values($groupedByHour);
            usort($result, function($a, $b) {
                return $a['hour'] - $b['hour'];
            });
            
            // Убираем первое значение (первый час)
            if (!empty($result)) {
                array_shift($result);
            }
        }

        return $this->successResponse($result);
    }
}

