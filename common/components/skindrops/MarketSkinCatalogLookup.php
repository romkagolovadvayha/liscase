<?php

namespace common\components\skindrops;

/**
 * Поиск позиции в массиве rust.tm / CSGO market из {@see RustTm::items()} / {@see CsGoMarket::items()}.
 * Ключи каталога — строки вида «1166467671_0» (classid_instance), не только число.
 */
final class MarketSkinCatalogLookup
{
    /**
     * @param array<int|string, array<string, mixed>> $data
     * @param string|int $id id из URL или формы
     * @return array<string, mixed>|null
     */
    public static function findItem(array $data, $id): ?array
    {
        if (array_key_exists($id, $data)) {
            return $data[$id];
        }
        if (is_numeric($id)) {
            $intId = (int) $id;
            if (array_key_exists($intId, $data)) {
                return $data[$intId];
            }
            $strId = (string) $intId;
            if (array_key_exists($strId, $data)) {
                return $data[$strId];
            }
        }

        $idStr = (string) $id;
        if ($idStr !== '' && preg_match('/^\d+$/', $idStr)) {
            $prefix = $idStr . '_';
            $found = null;
            foreach ($data as $key => $row) {
                $ks = (string) $key;
                if (str_starts_with($ks, $prefix)) {
                    if ($found !== null) {
                        return null;
                    }
                    $found = $row;
                }
            }
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
