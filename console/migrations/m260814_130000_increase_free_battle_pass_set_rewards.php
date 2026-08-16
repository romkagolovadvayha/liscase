<?php

use console\components\migration\Migration;

/**
 * Keeps the effective value of Season 1 rewards increasing by task position.
 *
 * The initial rebalance left free-track sets at one package while neighbouring
 * regular items already used the tier multiplier. That made the reward value
 * fall at every set position.
 */
class m260814_130000_increase_free_battle_pass_set_rewards extends Migration
{
    public function safeUp()
    {
        $seasonId = (int)$this->db->createCommand(
            "SELECT id FROM battle_pass_season WHERE slug = 'season-1'"
        )->queryScalar();
        if (!$seasonId) {
            throw new RuntimeException('Сезон Battle Pass season-1 не найден.');
        }

        $this->execute(
            "UPDATE tasks_v2 t
             INNER JOIN `drop` d ON d.id = t.reward_item_id
             SET t.reward_amount = 1 + FLOOR((t.battle_pass_position - 1) / 20),
                 t.updated_at = NOW()
             WHERE t.battle_pass_season_id = :seasonId
               AND t.type = 'battle_pass'
               AND t.battle_pass_position BETWEEN 1 AND 80
               AND d.drop_type = 2",
            [':seasonId' => $seasonId]
        );
    }

    public function safeDown()
    {
        $seasonId = (int)$this->db->createCommand(
            "SELECT id FROM battle_pass_season WHERE slug = 'season-1'"
        )->queryScalar();
        if (!$seasonId) {
            return;
        }

        $this->execute(
            "UPDATE tasks_v2 t
             INNER JOIN `drop` d ON d.id = t.reward_item_id
             SET t.reward_amount = 1,
                 t.updated_at = NOW()
             WHERE t.battle_pass_season_id = :seasonId
               AND t.type = 'battle_pass'
               AND t.battle_pass_position BETWEEN 1 AND 80
               AND d.drop_type = 2",
            [':seasonId' => $seasonId]
        );
    }
}
