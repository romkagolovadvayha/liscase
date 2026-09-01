<?php

namespace common\tests\unit\components\support;

use Codeception\Test\Unit;
use common\components\support\SupportStickerMessengerMedia;

class SupportStickerMessengerMediaTest extends Unit
{
    public function testResolvesRelativeStickerUrl(): void
    {
        $this->assertSame(
            'https://prostoj.store/stickers/17.webp',
            SupportStickerMessengerMedia::absoluteUrl('/stickers/17.webp', 'prostoj.store')
        );
        $this->assertNull(
            SupportStickerMessengerMedia::absoluteUrl('http://prostoj.store/stickers/17.webp', 'prostoj.store')
        );
    }

    public function testConvertsWebpStickerToPng(): void
    {
        $sourcePath = dirname(__DIR__, 5) . '/frontend/web/stickers/17-shcha-pochinyu.webp';
        $source = file_get_contents($sourcePath);

        $this->assertIsString($source);
        $png = SupportStickerMessengerMedia::webpToPng($source);

        $this->assertIsString($png);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        $this->assertSame([512, 512], array_slice(getimagesizefromstring($png), 0, 2));
    }
}
