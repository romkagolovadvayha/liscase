<?php

use common\models\tasks_v2\TaskV2;
use console\components\migration\Migration;
use yii\helpers\Json;

/**
 * Makes the fifth Season 1 task check whether the personal Telegram bot is linked.
 */
class m260814_140000_set_battle_pass_task_five_telegram extends Migration
{
    private const SEASON_SLUG = 'season-1';
    private const TASK_POSITION = 5;

    public function safeUp()
    {
        $seasonId = $this->findSeasonId();
        if ($seasonId === null) {
            throw new RuntimeException('Battle Pass Season 1 not found.');
        }

        if (!$this->taskExists($seasonId)) {
            throw new RuntimeException('Battle Pass task #5 not found.');
        }

        $this->update('tasks_v2', [
            'title' => 'Привязать Telegram-бота',
            'short_description' => 'Привяжите Telegram-бота',
            'full_description' => 'Привяжите Telegram-бота к аккаунту. Если бот уже привязан, условие выполнено — останется получить награду.',
            'check_type' => TaskV2::CHECK_TYPE_TELEGRAM_CONNECTED,
            'check_params' => Json::encode([]),
            'max_progress' => 1,
            'button_text' => 'Проверить и получить награду',
            'extra_buttons' => Json::encode([]),
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'battle_pass_season_id' => $seasonId,
            'battle_pass_position' => self::TASK_POSITION,
            'type' => TaskV2::TYPE_BATTLE_PASS,
        ]);
    }

    public function safeDown()
    {
        $seasonId = $this->findSeasonId();
        if ($seasonId === null) {
            return;
        }

        $this->update('tasks_v2', [
            'title' => 'Охота за скрапом · Разведчик',
            'short_description' => 'Соберите скрап: 500',
            'full_description' => 'Задание бесплатной дорожки №5. Прогресс считается только после завершения предыдущего задания.',
            'check_type' => TaskV2::CHECK_TYPE_STATISTICS_PARAM,
            'check_params' => Json::encode([
                'stat_key' => 'scrap',
                'required_value' => 500,
                'sum_all_servers' => true,
            ]),
            'max_progress' => 500,
            'button_text' => 'Проверить прогресс',
            'extra_buttons' => Json::encode([]),
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'battle_pass_season_id' => $seasonId,
            'battle_pass_position' => self::TASK_POSITION,
            'type' => TaskV2::TYPE_BATTLE_PASS,
        ]);
    }

    private function findSeasonId(): ?int
    {
        $seasonId = $this->db->createCommand(
            'SELECT id FROM battle_pass_season WHERE slug = :slug',
            [':slug' => self::SEASON_SLUG]
        )->queryScalar();

        return $seasonId === false ? null : (int)$seasonId;
    }

    private function taskExists(int $seasonId): bool
    {
        return (bool)$this->db->createCommand(
            'SELECT id FROM tasks_v2
             WHERE battle_pass_season_id = :seasonId
               AND battle_pass_position = :position
               AND type = :type',
            [
                ':seasonId' => $seasonId,
                ':position' => self::TASK_POSITION,
                ':type' => TaskV2::TYPE_BATTLE_PASS,
            ]
        )->queryScalar();
    }
}
