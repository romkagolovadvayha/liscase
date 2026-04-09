<?php

namespace common\helpers;

/**
 * Квадрат карты Rust (как SignStatistics / MapHelper.PositionToString в плагинах): сетка 146.3, буква A–Z по X.
 */
final class RustMapGridHelper
{
    private const CELL = 146.3;

    /**
     * @param float $x мировая координата X
     * @param float $z мировая координата Z
     */
    public static function positionToSquare(float $x, float $z, int $worldSize): string
    {
        if ($worldSize <= 0) {
            return '?';
        }
        $half = $worldSize / 2.0;
        $fx = (int) floor(($x + $half) / self::CELL);
        $xi = $fx % 26;
        if ($xi < 0) {
            $xi += 26;
        }
        $letter = chr(ord('A') + $xi);
        $zRow = (int) floor($worldSize / self::CELL) - (int) floor(($z + $half) / self::CELL);

        return $letter . $zRow;
    }
}
