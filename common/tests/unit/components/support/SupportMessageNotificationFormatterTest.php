<?php

namespace common\tests\unit\components\support;

use Codeception\Test\Unit;
use common\components\support\SupportMessageNotificationFormatter;

class SupportMessageNotificationFormatterTest extends Unit
{
    public function testFormatsImageStickerUsingItsName(): void
    {
        $message = '<img src="https://cdn.example/01-privet.webp" class="support_sticker" alt="Привет!" />';

        $this->assertSame('🖼 Стикер «Привет!»', SupportMessageNotificationFormatter::format($message));
    }

    public function testFormatsHtmlEncodedSticker(): void
    {
        $message = '&lt;img src=&quot;/stickers/11.webp&quot; class=&quot;support_sticker&quot; '
            . 'alt=&quot;стикер 11&quot; /&gt;';

        $this->assertSame('🖼 Стикер «11»', SupportMessageNotificationFormatter::format($message));
    }

    public function testFormatsVideoStickerUsingItsFileName(): void
    {
        $message = '<video class="support_sticker" autoplay muted>'
            . '<source src="/stickers/14.webm" type="video/webm">Ваш браузер не поддерживает видео.'
            . '</video>';

        $this->assertSame('🖼 Стикер «14»', SupportMessageNotificationFormatter::format($message));
    }

    public function testPreservesTextNextToSticker(): void
    {
        $message = 'Смотрите: <img class="support_sticker featured" src="/stickers/32.webp" title="Красава!">';

        $this->assertSame(
            'Смотрите: 🖼 Стикер «Красава!»',
            SupportMessageNotificationFormatter::format($message)
        );
    }

    public function testUsesAttachmentFallbackForEmptyMessage(): void
    {
        $this->assertSame('[вложения]', SupportMessageNotificationFormatter::format('&nbsp;', true));
    }
}
