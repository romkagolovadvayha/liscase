<?php

use common\services\battle_pass\BattlePassRewardAllocator;
use console\components\migration\Migration;

/**
 * Makes Season 1 rewards match store package sizes and adds sets to both tracks.
 */
class m260813_222000_rebalance_battle_pass_rewards extends Migration
{
    private const FREE_TASKS = 80;

    public function safeUp()
    {
        $seasonId = (int)$this->db->createCommand(
            "SELECT id FROM battle_pass_season WHERE slug = 'season-1'"
        )->queryScalar();
        if (!$seasonId) {
            throw new RuntimeException('Сезон Battle Pass season-1 не найден.');
        }

        $products = $this->loadEligibleProducts();
        $allocation = BattlePassRewardAllocator::allocate($products);
        $freeRewards = $allocation['free'];
        $vipRegular = $allocation['vip_regular'];
        $vipSets = $allocation['vip_sets'];

        if (count($freeRewards) !== self::FREE_TASKS) {
            throw new RuntimeException('Не удалось собрать ровно 80 бесплатных наград Battle Pass.');
        }

        $tasks = $this->db->createCommand(
            'SELECT id, battle_pass_position FROM tasks_v2
             WHERE battle_pass_season_id = :seasonId AND type = :type
             ORDER BY battle_pass_position ASC',
            [':seasonId' => $seasonId, ':type' => 'battle_pass']
        )->queryAll();
        if (count($tasks) !== 100) {
            throw new RuntimeException('В первом сезоне должно быть ровно 100 заданий.');
        }

        foreach ($tasks as $task) {
            $position = (int)$task['battle_pass_position'];
            if ($position <= self::FREE_TASKS) {
                $reward = $freeRewards[$position - 1];
                // reward_amount is a number of store packages. The API multiplies
                // it by drop.count, so resources never become a single item.
                // Every reward is a store package. Later tiers award more
                // packages, including sets, so the effective prize value never
                // drops when the task number grows.
                $packageMultiplier = 1 + intdiv($position - 1, 20);
            } elseif ($position <= 98) {
                // The VIP track continues with 18 valuable regular store products.
                $reward = $vipRegular[$position - 81];
                $packageMultiplier = 1;
            } else {
                // Only the final two VIP tasks award the two most expensive sets.
                $reward = $vipSets[$position - 99];
                $packageMultiplier = 1;
            }

            $this->update('tasks_v2', [
                'reward_type' => 'item',
                'reward_item_id' => (int)$reward['id'],
                'reward_currency' => null,
                'reward_amount' => $packageMultiplier,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int)$task['id']]);
        }

        $finalReward = $freeRewards[count($freeRewards) - 1];
        $this->update('battle_pass_season', [
            'reward_type' => 'item',
            'reward_item_id' => (int)$finalReward['id'],
            'reward_currency' => null,
            'reward_amount' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $seasonId]);
    }

    public function safeDown()
    {
        $seasonId = (int)$this->db->createCommand(
            "SELECT id FROM battle_pass_season WHERE slug = 'season-1'"
        )->queryScalar();
        if (!$seasonId) {
            return;
        }

        $oldProducts = $this->db->createCommand(
            'SELECT d.id, d.price
             FROM `drop` d
             WHERE d.status = 1
               AND d.market_status = 1
               AND d.drop_type = 0
               AND d.price > 0
               AND EXISTS (SELECT 1 FROM drop_image di WHERE di.drop_id = d.id AND di.type = 1)
             ORDER BY d.price ASC, d.id ASC'
        )->queryAll();
        if (!$oldProducts) {
            throw new RuntimeException('Не найдены товары для восстановления прежних наград Battle Pass.');
        }

        $tasks = $this->db->createCommand(
            'SELECT id, battle_pass_position FROM tasks_v2
             WHERE battle_pass_season_id = :seasonId AND type = :type
             ORDER BY battle_pass_position ASC',
            [':seasonId' => $seasonId, ':type' => 'battle_pass']
        )->queryAll();
        $lastIndex = count($oldProducts) - 1;
        foreach ($tasks as $task) {
            $position = (int)$task['battle_pass_position'];
            $rewardIndex = (int)floor((($position - 1) * $lastIndex) / 99);
            $this->update('tasks_v2', [
                'reward_item_id' => (int)$oldProducts[$rewardIndex]['id'],
                'reward_amount' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int)$task['id']]);
        }

        $this->update('battle_pass_season', [
            'reward_item_id' => (int)$oldProducts[$lastIndex]['id'],
            'reward_amount' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $seasonId]);
    }

    private function loadEligibleProducts(): array
    {
        return $this->db->createCommand(
            "SELECT d.id, d.price, d.count, d.drop_type
             FROM `drop` d
             WHERE d.status = 1
               AND d.market_status = 1
               AND d.price > 0
               AND d.drop_type IN (0, 1, 2)
               AND COALESCE(d.command, '') NOT LIKE '%bypassqueue%'
               AND COALESCE(d.command, '') NOT LIKE '%blueprintmanager%'
               AND LOWER(COALESCE(d.eng_name, '')) NOT LIKE '%queue%'
               AND LOWER(COALESCE(d.eng_name, '')) NOT LIKE '%blueprint%'
               AND EXISTS (SELECT 1 FROM drop_image di WHERE di.drop_id = d.id AND di.type = 1)
             ORDER BY d.price ASC, d.id ASC"
        )->queryAll();
    }

}
