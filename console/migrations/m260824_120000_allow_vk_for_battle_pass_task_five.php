<?php

use common\models\tasks_v2\TaskV2;
use console\components\migration\Migration;
use yii\helpers\Json;

/**
 * Allows completing the fifth Season 1 task by linking either Telegram or VK.
 */
class m260824_120000_allow_vk_for_battle_pass_task_five extends Migration
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

        $this->updateTask($seasonId, [
            'title' => 'Привязать Telegram-бота или бота ВКонтакте',
            'short_description' => 'Привяжите Telegram-бота или бота ВКонтакте',
            'full_description' => 'Привяжите к аккаунту Telegram-бота или бота ВКонтакте. Достаточно подключить одного из ботов. Если один из них уже привязан, условие выполнено — останется получить награду.',
            'check_type' => TaskV2::CHECK_TYPE_MESSENGER_CONNECTED,
        ]);
    }

    public function safeDown()
    {
        $seasonId = $this->findSeasonId();
        if ($seasonId === null || !$this->taskExists($seasonId)) {
            return;
        }

        $this->updateTask($seasonId, [
            'title' => 'Привязать Telegram-бота',
            'short_description' => 'Привяжите Telegram-бота',
            'full_description' => 'Привяжите Telegram-бота к аккаунту. Если бот уже привязан, условие выполнено — останется получить награду.',
            'check_type' => TaskV2::CHECK_TYPE_TELEGRAM_CONNECTED,
        ]);
    }

    private function updateTask(int $seasonId, array $attributes): void
    {
        $this->update('tasks_v2', array_merge($attributes, [
            'check_params' => Json::encode([]),
            'max_progress' => 1,
            'button_text' => 'Проверить и получить награду',
            'extra_buttons' => Json::encode([]),
            'updated_at' => date('Y-m-d H:i:s'),
        ]), [
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
