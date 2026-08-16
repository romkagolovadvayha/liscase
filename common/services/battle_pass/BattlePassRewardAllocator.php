<?php

namespace common\services\battle_pass;

use RuntimeException;

/**
 * Selects a stable 80/20 reward split from a store catalogue of any valid size.
 */
final class BattlePassRewardAllocator
{
    public const FREE_REWARD_COUNT = 80;
    public const MAX_FREE_SET_REWARDS = 7;
    public const VIP_REGULAR_REWARD_COUNT = 18;
    public const VIP_SET_REWARD_COUNT = 2;

    /**
     * Products must be ordered by price and then by id.
     *
     * @return array{free: array<int, array>, vip_regular: array<int, array>, vip_sets: array<int, array>}
     */
    public static function allocate(array $products): array
    {
        $sets = array_values(array_filter($products, static function (array $product): bool {
            return (int)$product['drop_type'] === 2;
        }));
        $regular = array_values(array_filter($products, static function (array $product): bool {
            return (int)$product['drop_type'] !== 2;
        }));

        if (count($sets) <= self::VIP_SET_REWARD_COUNT) {
            throw new RuntimeException('At least three active sets are required for Battle Pass rewards.');
        }

        // Keep the two most expensive sets exclusive to the final VIP tasks.
        $vipSets = array_splice($sets, -self::VIP_SET_REWARD_COUNT);
        $freeSetCount = min(self::MAX_FREE_SET_REWARDS, count($sets));
        $freeSets = self::sampleEvenly($sets, $freeSetCount);
        $freeRegularCount = self::FREE_REWARD_COUNT - $freeSetCount;

        if (count($regular) < $freeRegularCount) {
            throw new RuntimeException(sprintf(
                'At least %d active regular products are required for Battle Pass rewards; %d found.',
                $freeRegularCount,
                count($regular)
            ));
        }

        $freeRewards = array_merge(
            self::sampleEvenly($regular, $freeRegularCount),
            $freeSets
        );
        usort($freeRewards, static function (array $left, array $right): int {
            $byPrice = (float)$left['price'] <=> (float)$right['price'];
            return $byPrice !== 0 ? $byPrice : ((int)$left['id'] <=> (int)$right['id']);
        });

        return [
            'free' => $freeRewards,
            'vip_regular' => array_slice($regular, -self::VIP_REGULAR_REWARD_COUNT),
            'vip_sets' => $vipSets,
        ];
    }

    private static function sampleEvenly(array $products, int $count): array
    {
        if ($count === 1) {
            return [$products[0]];
        }

        $sample = [];
        $lastIndex = count($products) - 1;
        for ($index = 0; $index < $count; $index++) {
            $sourceIndex = (int)round(($index * $lastIndex) / ($count - 1));
            $sample[] = $products[$sourceIndex];
        }

        return $sample;
    }
}
