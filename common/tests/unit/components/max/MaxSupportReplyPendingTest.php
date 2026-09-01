<?php

namespace common\tests\unit\components\max;

use Codeception\Test\Unit;
use common\components\max\MaxSupportReplyService;
use Yii;

class MaxSupportReplyPendingTest extends Unit
{
    public function testFindsPendingReplyByChatWhenChannelPostHasNoSender(): void
    {
        $reflection = new \ReflectionClass(MaxSupportReplyService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $chatKeyMethod = $reflection->getMethod('pendingChatCacheKey');
        $chatKeyMethod->setAccessible(true);
        $findMethod = $reflection->getMethod('findPending');
        $findMethod->setAccessible(true);

        $chatId = '-987654321';
        $pending = [
            'ticketNumber' => 53304,
            'supportChatId' => $chatId,
            'operatorMaxId' => '49548174',
        ];
        $chatKey = $chatKeyMethod->invoke($service, $chatId);
        Yii::$app->cache->set($chatKey, $pending, 60);

        try {
            $this->assertSame($pending, $findMethod->invoke($service, $chatId, null));
            $this->assertNull($findMethod->invoke($service, $chatId, '111111'));
        } finally {
            Yii::$app->cache->delete($chatKey);
        }
    }
}
