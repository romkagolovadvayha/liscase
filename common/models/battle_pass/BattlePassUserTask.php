<?php

namespace common\models\battle_pass;

use common\components\base\ActiveRecord;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;

/**
 * @property int $id
 * @property int $season_id
 * @property int $task_id
 * @property int $user_id
 * @property int $baseline_value
 * @property string $unlocked_at
 */
class BattlePassUserTask extends ActiveRecord
{
    public static function tableName()
    {
        return 'battle_pass_user_task';
    }

    public function rules()
    {
        return [
            [['season_id', 'task_id', 'user_id', 'unlocked_at'], 'required'],
            [['season_id', 'task_id', 'user_id', 'baseline_value'], 'integer'],
            [['unlocked_at'], 'safe'],
            [['season_id'], 'exist', 'targetClass' => BattlePassSeason::class, 'targetAttribute' => ['season_id' => 'id']],
            [['task_id'], 'exist', 'targetClass' => TaskV2::class, 'targetAttribute' => ['task_id' => 'id']],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public static function unlock(int $seasonId, int $taskId, int $userId, int $baselineValue): self
    {
        $model = static::findOne(['user_id' => $userId, 'task_id' => $taskId]);
        if ($model) {
            return $model;
        }

        $model = new static();
        $model->season_id = $seasonId;
        $model->task_id = $taskId;
        $model->user_id = $userId;
        $model->baseline_value = max(0, $baselineValue);
        $model->unlocked_at = date('Y-m-d H:i:s');
        try {
            if (!$model->save()) {
                throw new \RuntimeException('Не удалось открыть задание Battle Pass: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE));
            }
        } catch (\yii\db\IntegrityException $e) {
            // Два параллельных запроса могли одновременно открыть одно задание.
            $existing = static::findOne(['user_id' => $userId, 'task_id' => $taskId]);
            if ($existing) {
                return $existing;
            }
            throw $e;
        }
        return $model;
    }
}
