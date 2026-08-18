<?php

namespace common\tests\unit\services;

use common\services\battle_pass\BattlePassRewardAllocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BattlePassRewardAllocatorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 4)
            . '/common/services/battle_pass/BattlePassRewardAllocator.php';
    }

    #[DataProvider('catalogueSizes')]
    public function testAlwaysAllocatesExactlyOneHundredRewards(
        int $regularCount,
        int $setCount,
        int $expectedFreeSets
    ): void {
        $products = array_merge(
            $this->products($regularCount, 0, 1),
            $this->products($setCount, 2, 1001)
        );

        $allocation = BattlePassRewardAllocator::allocate($products);

        self::assertCount(80, $allocation['free']);
        self::assertCount(18, $allocation['vip_regular']);
        self::assertCount(2, $allocation['vip_sets']);
        self::assertCount($expectedFreeSets, array_filter(
            $allocation['free'],
            static function (array $product): bool {
                return (int)$product['drop_type'] === 2;
            }
        ));
    }

    public static function catalogueSizes(): array
    {
        return [
            'minimum set catalogue' => [79, 3, 1],
            'expected nine-set catalogue' => [73, 9, 7],
            'larger production catalogue' => [73, 14, 7],
        ];
    }

    public function testReservesTheTwoMostExpensiveSetsForVip(): void
    {
        $products = array_merge(
            $this->products(73, 0, 1),
            $this->products(12, 2, 1001)
        );

        $allocation = BattlePassRewardAllocator::allocate($products);

        self::assertSame([1011, 1012], array_column($allocation['vip_sets'], 'id'));
        self::assertNotContains(1011, array_column($allocation['free'], 'id'));
        self::assertNotContains(1012, array_column($allocation['free'], 'id'));
    }

    public function testRejectsCatalogueWithoutEnoughRegularProducts(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least 73 active regular products');

        BattlePassRewardAllocator::allocate(array_merge(
            $this->products(72, 0, 1),
            $this->products(9, 2, 1001)
        ));
    }

    private function products(int $count, int $dropType, int $firstId): array
    {
        $products = [];
        for ($index = 0; $index < $count; $index++) {
            $id = $firstId + $index;
            $products[] = [
                'id' => $id,
                'price' => $id,
                'count' => 1,
                'drop_type' => $dropType,
            ];
        }

        return $products;
    }
}
