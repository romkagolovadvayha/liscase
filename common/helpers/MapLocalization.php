<?php

namespace common\helpers;

class MapLocalization
{
    private const BIOME_BASE = [
        's' => 'Snow',
        'd' => 'Desert',
        'f' => 'Forest',
        't' => 'Tundra',
        'j' => 'Jungle',
        'arctic' => 'Arctic',
    ];

    private const BIOME_TRANSLATIONS = [
        'ru-RU' => [
            's' => 'Снег',
            'd' => 'Пустыня',
            'f' => 'Лес',
            't' => 'Тундра',
            'j' => 'Джунгли',
            'arctic' => 'Арктика',
        ],
    ];

    private const MONUMENT_TRANSLATIONS = [
        'ru-RU' => [
            'Airfield' => 'Аэродром',
            'Bandit Town' => 'Посёлок бандитов',
            'Ferry Terminal' => 'Паромный терминал',
            'Outpost' => 'Аванпост',
            'Excavator' => 'Карьер',
            'Launch Site' => 'Космодром',
            'Military Tunnels' => 'Военные туннели',
            'Water Treatment' => 'Очистные сооружения',
            'Trainyard' => 'Ж/д депо',
            'Powerplant' => 'Электростанция',
            'Junkyard' => 'Свалка',
            'Nuclear Missile Silo' => 'Ракетная шахта',
            'Satellite Dish' => 'Спутниковая тарелка',
            'Sphere Tank' => 'Купол',
            'Sewer Branch' => 'Канализация',
            'Large Harbor' => 'Большой порт',
            'Small Harbor' => 'Малый порт',
            'Fishing Village A' => 'Рыбацкая деревня A',
            'Fishing Village B' => 'Рыбацкая деревня B',
            'Fishing Village C' => 'Рыбацкая деревня C',
            'Gas Station' => 'Заправка',
            'Supermarket' => 'Супермаркет',
            'Warehouse' => 'Склад',
            'Ranch' => 'Ранчо',
            'Large Barn' => 'Большой амбар',
            'Military Base D' => 'Военная база',
            'Arctic Research Base A' => 'Арктическая база A',
            'Sulfur Quarry' => 'Серный карьер',
            'Hqm Quarry' => 'Карьер МВК',
            'Stone Quarry' => 'Каменоломня',
            'Water Well A' => 'Водяная скважина A',
            'Water Well B' => 'Водяная скважина B',
            'Water Well C' => 'Водяная скважина C',
            'Water Well D' => 'Водяная скважина D',
            'Water Well E' => 'Водяная скважина E',
            'Oilrig' => 'Малая нефтевышка',
            'Large Oilrig' => 'Большая нефтевышка',
            'Lighthouse' => 'Маяк',
            'Cave Small Easy' => 'Пещера малая (легко)',
            'Cave Small Medium' => 'Пещера малая (средне)',
            'Cave Small Hard' => 'Пещера малая (сложно)',
            'Cave Medium Easy' => 'Пещера средняя (легко)',
            'Cave Medium Medium' => 'Пещера средняя (средне)',
            'Cave Medium Hard' => 'Пещера средняя (сложно)',
            'Cave Large Sewers Hard' => 'Большая пещера (канализация)',
            'Underwater Lab' => 'Подводная лаборатория',
            'Underwater Lab B' => 'Подводная лаборатория B',
            'Ice Lake 4' => 'Ледяное озеро',
            'Iceberg 2' => 'Ледяная глыба',
            'Iceberg 4' => 'Ледяная глыба',
            'Canyon' => 'Каньон',
            'Lake A' => 'Озеро A',
            'Lake B' => 'Озеро B',
            'Lake C' => 'Озеро C',
            'Oasis' => 'Оазис',
            'Swamp A' => 'Болото A',
            'Swamp B' => 'Болото B',
            'Power Substation' => 'Подстанция',
            'Power Substation Small 1' => 'Малая подстанция 1',
            'Power Substation Small 2' => 'Малая подстанция 2',
            'Power Substation Big 1' => 'Большая подстанция 1',
            'Power Substation Big 2' => 'Большая подстанция 2',
            'Tunnel Entrance' => 'Вход в туннель',
            'Tunnel Entrance Transition' => 'Переход в туннель',
            'Radtown' => 'Радтаун',
            'Train Station Above Ground' => 'Ж/д станция',
            'Powerline A' => 'ЛЭП A',
            'Powerline B' => 'ЛЭП B',
            'Powerline D' => 'ЛЭП D',
            'Anvil Rock' => 'Скала Наковальня',
            'Tiny God Rock' => 'Малый бог-камень',
            'Medium God Rock' => 'Средний бог-камень',
            'Large God Rock' => 'Большой бог-камень',
            '3 Wall Rock' => 'Тройная скала',
            'Ziggurat' => 'Зиккурат',
            'Ruin A' => 'Руины A',
            'Ruin C' => 'Руины C',
        ],
    ];

    public static function biome(string $code, string $language): string
    {
        $labels = self::biomeLabels($language);

        return $labels[$code] ?? $code;
    }

    public static function monument(string $type, string $language): string
    {
        if (isset(self::MONUMENT_TRANSLATIONS[$language][$type])) {
            return self::MONUMENT_TRANSLATIONS[$language][$type];
        }

        return self::MONUMENT_TRANSLATIONS['ru-RU'][$type] ?? $type;
    }

    public static function biomeLabels(string $language): array
    {
        if (isset(self::BIOME_TRANSLATIONS[$language])) {
            return array_merge(self::BIOME_BASE, self::BIOME_TRANSLATIONS[$language]);
        }

        return self::BIOME_BASE;
    }
}

