<?php

namespace common\components\support;

use common\models\support\Support;

/**
 * Общий payload кнопки ответа для Telegram и MAX.
 */
final class SupportReplyCallback
{
    private const PREFIX = 'support-reply:';

    public static function build(int $ticketNumber): string
    {
        return self::PREFIX . $ticketNumber;
    }

    public static function parse(string $payload): ?int
    {
        if (!preg_match('/^' . preg_quote(self::PREFIX, '/') . '(\d{1,12})$/', $payload, $matches)) {
            return null;
        }

        $ticketNumber = (int)$matches[1];

        return $ticketNumber > Support::NUMBER_OFFSET ? $ticketNumber : null;
    }
}
