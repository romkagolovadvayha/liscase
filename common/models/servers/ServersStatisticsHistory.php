<?php

namespace common\models\servers;

use common\components\base\ActiveRecord;
use Yii;

/**
 * This is the model class for table "servers_statistics_history".
 *
 * @property int $id
 * @property int $server_id
 * @property int $players
 * @property int $joined
 * @property int $queued
 * @property string $created_at
 *
 * @property Servers $server
 */
class ServersStatisticsHistory extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%servers_statistics_history}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['server_id', 'players', 'joined', 'queued'], 'required'],
            [['server_id', 'players', 'joined', 'queued'], 'integer'],
            [['created_at'], 'safe'],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'server_id' => 'ID сервера',
            'players' => 'Текущий онлайн',
            'joined' => 'Игроки в очереди',
            'queued' => 'Подключающиеся',
            'created_at' => 'Время записи',
        ];
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    /**
     * Сохраняет или обновляет статистику сервера для текущего часа
     * В час может быть только одна запись для сервера - если она есть, обновляется, если нет - создается
     * Также удаляет записи старше 30 дней
     * 
     * @param int $serverId ID сервера
     * @param int $players Текущий онлайн
     * @param int $joined Игроки в очереди
     * @param int $queued Подключающиеся
     * @return bool
     */
    public static function saveOrUpdateHourlyStats($serverId, $players, $joined, $queued)
    {
        try {
            // Удаляем записи старше 30 дней (периодически, не каждый раз)
            // Проверяем только раз в час, чтобы не нагружать БД
            $lastCleanup = Yii::$app->cache->get('servers_statistics_history_last_cleanup');
            if ($lastCleanup === false) {
                self::deleteOldRecords();
                Yii::$app->cache->set('servers_statistics_history_last_cleanup', time(), 3600); // Кэш на 1 час
            }

            // Получаем начало текущего часа (например, 2024-01-01 14:00:00)
            $currentHourStart = date('Y-m-d H:00:00');
            $nextHourStart = date('Y-m-d H:00:00', strtotime('+1 hour', strtotime($currentHourStart)));
            
            // Ищем запись для этого сервера в текущем часе
            // Используем DATE_FORMAT для точного сравнения по часу
            $model = self::find()
                ->where(['server_id' => $serverId])
                ->andWhere(['>=', 'created_at', $currentHourStart])
                ->andWhere(['<', 'created_at', $nextHourStart])
                ->orderBy(['created_at' => SORT_DESC])
                ->one();
            
            if ($model) {
                // Обновляем существующую запись для текущего часа
                // Но только если новые значения players и queued больше или равны текущим
                if ($players >= $model->players && $queued >= $model->queued) {
                    $model->players = $players;
                    $model->joined = $joined;
                    $model->queued = $queued;
                    return $model->save(false);
                }
                // Если значения меньше, не обновляем
                return true;
            } else {
                // Создаем новую запись для текущего часа
                $model = new self();
                $model->server_id = $serverId;
                $model->players = $players;
                $model->joined = $joined;
                $model->queued = $queued;
                $model->created_at = date('Y-m-d H:i:s');
                return $model->save(false);
            }
        } catch (\Exception $e) {
            Yii::error("ServersStatisticsHistory::saveOrUpdateHourlyStats error: " . $e->getMessage(), 'servers_statistics');
            return false;
        }
    }

    /**
     * Удаляет записи старше 30 дней
     * 
     * @return int Количество удаленных записей
     */
    public static function deleteOldRecords()
    {
        try {
            // Дата 30 дней назад
            $date30DaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
            
            // Удаляем все записи старше 30 дней
            $deleted = self::deleteAll(['<', 'created_at', $date30DaysAgo]);
            
            if ($deleted > 0) {
                Yii::info("ServersStatisticsHistory::deleteOldRecords deleted {$deleted} records older than 30 days", 'servers_statistics');
            }
            
            return $deleted;
        } catch (\Exception $e) {
            Yii::error("ServersStatisticsHistory::deleteOldRecords error: " . $e->getMessage(), 'servers_statistics');
            return 0;
        }
    }
}

