<?php

namespace common\tests\unit\components\payments;

use common\components\merchant\TBankMerchantAPI;
use PHPUnit\Framework\TestCase;

final class TBankMerchantAPITest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 5)
            . '/common/components/merchant/TBankMerchantAPI.php';
    }

    public function testValidatesOfficialNotificationTokenExample(): void
    {
        $payload = [
            'TerminalKey' => '1234567890DEMO',
            'OrderId' => '000000',
            'Success' => true,
            'Status' => 'AUTHORIZED',
            'PaymentId' => '0000000',
            'ErrorCode' => '0',
            'Amount' => '1111',
            'CardId' => '000000',
            'Pan' => '200000******0000',
            'ExpDate' => '1111',
            'RebillId' => '000000',
            'Token' => '1c0964277d0213349243065a0d5b838b8e90d2d25f740d0f2767836e710e80c8',
        ];

        $api = new TBankMerchantAPI('1234567890DEMO', '11111111111');

        self::assertTrue($api->isValidNotification($payload));
    }

    public function testRejectsTamperedOrForeignNotifications(): void
    {
        $payload = $this->signedPayload();
        $api = new TBankMerchantAPI('terminal', 'secret');

        self::assertTrue($api->isValidNotification($payload));

        $payload['Amount'] = 200001;
        self::assertFalse($api->isValidNotification($payload));

        $payload = $this->signedPayload();
        $payload['TerminalKey'] = 'another-terminal';
        self::assertFalse($api->isValidNotification($payload));
    }

    private function signedPayload(): array
    {
        $payload = [
            'TerminalKey' => 'terminal',
            'OrderId' => '42',
            'Success' => true,
            'Status' => 'CONFIRMED',
            'PaymentId' => '123456789',
            'ErrorCode' => '0',
            'Amount' => 200000,
            'Data' => ['ignored' => 'nested'],
        ];

        $values = $payload;
        $values['Password'] = 'secret';
        ksort($values);
        $tokenSource = '';
        foreach ($values as $value) {
            if (is_array($value) || $value === null) {
                continue;
            }
            $tokenSource .= is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        }
        $payload['Token'] = hash('sha256', $tokenSource);

        return $payload;
    }
}
