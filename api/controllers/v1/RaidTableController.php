<?php

namespace api\controllers\v1;

use Yii;
use common\models\statistics\Statistics;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с калькулятором рейдов
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="RaidTable")
 */
class RaidTableController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // Все методы публичные, JWT не требуется
        return $behaviors;
    }

    /**
     * Получение данных для калькулятора рейдов
     * 
     * @OA\Get(
     *     path="/v1/raid-table",
     *     operationId="getRaidTable",
     *     tags={"RaidTable"},
     *     summary="Получить данные для калькулятора рейдов",
     *     description="Возвращает список целей, рецепты, изображения и названия предметов",
     *     @OA\Response(
     *         response=200,
     *         description="Данные для калькулятора рейдов",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_raid_calculator')) {
            return $this->errorResponse('RAID_CALCULATOR_DISABLED', 'Калькулятор рейдов отключен', [], 404);
        }

        // Кэшируем данные на 1 час (v2 = картинки 150px через getImageLarge)
        $cacheKey = 'api_raid_table_v4';
        $cache = Yii::$app->cache;
        $data = $cache->get($cacheKey);

        if ($data === false) {
            $images = Statistics::productsImages();
            $names = Statistics::productsNames();
            // Названия для целей/оружия, которых может не быть в Drop (калькулятор рейдов)
            $names = array_merge($names, [
                'shotgun-trap' => 'Гантрап',
                'arrow.fire' => 'Огненные стрелы',
                'jackhammer' => 'Отбойный молоток',
                'flame-thrower' => 'Огнемёт',
                'grenade.f1' => 'Граната F1',
                'chainsaw' => 'Бензопила',
                'hammer' => 'Молот',
                'rock' => 'Камень',
                'flashlight.held' => 'Фонарик',
                'torch' => 'Факел',
                'axe.salvaged' => 'Самодельный топор',
                'icepick.salvaged' => 'Ледоруб',
                'hatchet' => 'Топор',
                'pickaxe' => 'Кирка',
                'hatchet.stone' => 'Каменный топор',
                'pickaxe.stone' => 'Каменная кирка',
            ]);
            $recipes = $this->recipes();
            $items = $this->getRaidTableList($names, $images);

            $data = [
                'items' => $items,
                'recipes' => $recipes,
                'prodImages' => $images,
                'prodNames' => $names,
            ];

            // Сохраняем в кэш на 1 час (3600 секунд)
            $cache->set($cacheKey, $data, 3600);
        }

        return $this->successResponse($data);
    }

    /**
     * Рецепты для крафта
     */
    private function recipes(): array
    {
        return [
            // база
            'gunpowder' => [
                'yields' => 1,
                'ingredients' => ['charcoal' => 30, 'sulfur' => 20],
            ],
            'small-stash' => [
                'yields' => 1,
                'ingredients' => ['cloth' => 10],
            ],

            // взрывчатка (компонент)
            'explosives' => [
                'yields' => 1,
                'ingredients' => ['gunpowder' => 50, 'low_grade_fuel' => 3, 'sulfur' => 10, 'metal_fragments' => 10],
            ],

            // финишные предметы
            'c4thrown' => [
                'yields' => 1,
                'ingredients' => ['explosives' => 20, 'cloth' => 5, 'tech_trash' => 2],
            ],
            'grenade.beancan' => [
                'yields' => 1,
                'ingredients' => ['gunpowder' => 60, 'metal_fragments' => 20],
            ],
            'satchelsthrown' => [
                'yields' => 1,
                'ingredients' => ['grenade.beancan' => 4, 'small-stash' => 1, 'rope' => 1],
            ],
            'rocket_basic' => [
                'yields' => 1,
                'ingredients' => ['metal_pipes' => 2, 'explosives' => 10, 'gunpowder' => 150],
            ],
            'rocket_fire' => [
                'yields' => 1,
                'ingredients' => ['metal_pipes' => 2, 'low_grade_fuel' => 75, 'gunpowder' => 150],
            ],
            'rocket_hv' => [
                'yields' => 1,
                'ingredients' => ['metal_pipes' => 1, 'gunpowder' => 100],
            ],
            'ammo_explosive' => [
                'yields' => 1,
                'ingredients' => ['metal_fragments' => 10, 'gunpowder' => 20, 'sulfur' => 10],
            ],
            'grenade.molotov' => [
                'yields' => 1,
                'ingredients' => ['cloth' => 10, 'low_grade_fuel' => 50],
            ],
            'torpedo' => [
                'yields' => 1,
                'ingredients' => ['metal_pipes' => 1, 'gunpowder' => 30],
            ],
            'propane_bomb' => [
                'yields' => 1,
                'ingredients' => ['propane_tank' => 1, 'gunpowder' => 450, 'low_grade_fuel' => 20],
            ],
            'grenade.f1' => [
                'yields' => 1,
                'ingredients' => ['gunpowder' => 30, 'metal_fragments' => 25],
            ],
            'arrow.fire' => [
                'yields' => 1,
                'ingredients' => ['wood' => 25, 'cloth' => 5, 'low_grade_fuel' => 10],
            ],
            'jackhammer' => [
                'yields' => 1,
                'ingredients' => ['metal_fragments' => 150, 'gears' => 4, 'metal_blade' => 2],
            ],
            'flame-thrower' => [
                'yields' => 1,
                'ingredients' => ['metal_pipes' => 2, 'metal_fragments' => 100, 'tech_trash' => 2],
            ],
            'chainsaw' => [
                'yields' => 1,
                'ingredients' => ['metal_fragments' => 100, 'gears' => 4, 'metal_blade' => 2, 'low_grade_fuel' => 50],
            ],
            'hammer' => [
                'yields' => 1,
                'ingredients' => ['wood' => 15, 'metal_fragments' => 15],
            ],
            'rock' => [
                'yields' => 1,
                'ingredients' => ['wood' => 1],
            ],
            'flashlight.held' => [
                'yields' => 1,
                'ingredients' => ['metal_fragments' => 25, 'tincan' => 1],
            ],
            'torch' => [
                'yields' => 1,
                'ingredients' => ['wood' => 100, 'cloth' => 5, 'low_grade_fuel' => 10],
            ],
            'axe.salvaged' => [
                'yields' => 1,
                'ingredients' => ['metal_pipes' => 1, 'metal_blade' => 5],
            ],
            'icepick.salvaged' => [
                'yields' => 1,
                'ingredients' => ['metal_pipes' => 1, 'metal_blade' => 5],
            ],
            'hatchet' => [
                'yields' => 1,
                'ingredients' => ['wood' => 100, 'metal_fragments' => 50],
            ],
            'pickaxe' => [
                'yields' => 1,
                'ingredients' => ['wood' => 100, 'metal_fragments' => 75],
            ],
            'hatchet.stone' => [
                'yields' => 1,
                'ingredients' => ['wood' => 200, 'stones' => 100],
            ],
            'pickaxe.stone' => [
                'yields' => 1,
                'ingredients' => ['wood' => 200, 'stones' => 100],
            ],
        ];
    }

    /**
     * Получить список целей для рейдов
     */
    private function getRaidTableList($names, $images): array
    {
        // предпочтения «дешевле → дороже» (для подсказки)
        $preferredByTarget = [
            'wood-wall' => ['flame-thrower', 'grenade.molotov', 'handmade-shell', 'grenade.beancan', 'satchelsthrown', 'ammo_explosive', 'rocket_basic', 'c4thrown'],
            'wooden-door' => ['grenade.molotov', 'flame-thrower', 'handmade-shell', 'grenade.beancan', 'satchelsthrown', 'ammo_explosive', 'rocket_basic', 'c4thrown'],
            'stone-wall' => ['satchelsthrown', 'rocket_basic', 'c4thrown', 'ammo_explosive', 'grenade.beancan'],
            'sheet-metal-door' => ['satchelsthrown', 'c4thrown', 'rocket_basic', 'ammo_explosive', 'grenade.beancan'],
            'garage-door' => ['rocket_basic', 'c4thrown', 'ammo_explosive', 'satchelsthrown', 'grenade.beancan'],
            'armored-door' => ['c4thrown', 'rocket_basic', 'ammo_explosive', 'satchelsthrown'],
            'metal-wall' => ['rocket_basic', 'c4thrown', 'ammo_explosive', 'satchelsthrown'],
            'armored-wall' => ['c4thrown', 'rocket_basic', 'ammo_explosive', 'satchelsthrown'],

            'high-external-wood-wall' => ['flame-thrower', 'grenade.molotov', 'handmade-shell', 'grenade.beancan', 'satchelsthrown'],
            'high-external-stone-wall' => ['satchelsthrown', 'rocket_basic', 'c4thrown', 'ammo_explosive'],

            'window.grates.wood' => ['grenade.beancan', 'satchelsthrown', 'ammo_explosive'],
            'window.grates.metal' => ['satchelsthrown', 'ammo_explosive', 'rocket_basic'],
            'window.reinforced' => ['satchelsthrown', 'ammo_explosive', 'rocket_basic'],
            'shopfront.metal' => ['satchelsthrown', 'ammo_explosive', 'rocket_basic'],

            'auto-turret' => ['rocket_basic', 'ammo_explosive', 'satchelsthrown', 'grenade.beancan', 'c4thrown'],
            'sam.site' => ['rocket_basic', 'ammo_explosive', 'satchelsthrown'],
        ];

        // Полная матрица целей → (weaponKey, count)
        $defs = [
            // Люки
            ['key' => 'ladder-hatch', 'group' => 'Двери', 'weapons' => [
                ['hatchet', 75], ['hatchet.stone', 385], ['pickaxe.stone', 311],
                ['ammo_explosive', 63], ['c4thrown', 1], ['satchelsthrown', 4], ['rocket_basic', 2], ['40mm_grenade_he', 9],
                ['hammer', 24], ['rock', 148], ['flashlight.held', 257], ['torch', 385],
            ]],
            ['key' => 'triangle-hatch', 'group' => 'Двери', 'weapons' => [
                ['ammo_explosive', 63], ['c4thrown', 1], ['satchelsthrown', 4], ['rocket_basic', 2], ['40mm_grenade_he', 9],
            ]],

            // Стены
            ['key' => 'wood-wall', 'group' => 'Стены', 'softside' => true, 'weapons' => [
                ['axe.salvaged', 6], ['icepick.salvaged', 8], ['hatchet', 12], ['pickaxe', 11], ['hatchet.stone', 46], ['pickaxe.stone', 96],
                ['arrow.fire', 125], ['handmade-shell', 93], ['jackhammer', 22], ['chainsaw', 35], ['hammer', 58], ['rock', 368], ['flashlight.held', 642], ['torch', 962],
                ['flame-thrower', 206], ['grenade.molotov', 4], ['grenade.beancan', 13], ['satchelsthrown', 3],
                ['ammo_explosive', 49], ['rocket_basic', 2], ['rocket_fire', 1], ['c4thrown', 1], ['40mm_grenade_he', 9], ['grenade.f1', 4],
            ], 'notes' => 'Огонь эффективен; C4/ракеты — оверкилл.'],
            ['key' => 'stone-wall', 'group' => 'Стены', 'softside' => true, 'weapons' => [
                ['axe.salvaged', 80], ['icepick.salvaged', 54], ['hatchet', 167], ['pickaxe', 50], ['hatchet.stone', 1000], ['pickaxe.stone', 400],
                ['handmade-shell', 556], ['jackhammer', 88], ['chainsaw', 695], ['hammer', 116], ['rock', 736], ['flashlight.held', 1283], ['torch', 1924],
                ['grenade.beancan', 46], ['satchelsthrown', 10], ['ammo_explosive', 185], ['rocket_basic', 4],
                ['c4thrown', 2], ['40mm_grenade_he', 29], ['grenade.f1', 8],
            ]],
            ['key' => 'metal-wall', 'group' => 'Стены', 'weapons' => [
                ['axe.salvaged', 397], ['icepick.salvaged', 298], ['hatchet', 598], ['pickaxe', 345], ['hatchet.stone', 2565], ['pickaxe.stone', 1611],
                ['jackhammer', 439], ['chainsaw', 2778], ['hammer', 463], ['rock', 2942], ['flashlight.held', 5129], ['torch', 7693],
                ['grenade.beancan', 112], ['satchelsthrown', 23], ['ammo_explosive', 400], ['rocket_basic', 8], ['c4thrown', 4], ['40mm_grenade_he', 57], ['grenade.f1', 15],
            ]],
            ['key' => 'armored-wall', 'group' => 'Стены', 'weapons' => [
                ['axe.salvaged', 794], ['icepick.salvaged', 569], ['hatchet', 1195], ['pickaxe', 690], ['hatchet.stone', 5129], ['pickaxe.stone', 3221],
                ['jackhammer', 878], ['chainsaw', 5556], ['hammer', 926], ['rock', 5883], ['flashlight.held', 10257], ['torch', 15385],
                ['grenade.beancan', 223], ['satchelsthrown', 46], ['ammo_explosive', 799], ['rocket_basic', 15], ['c4thrown', 8], ['40mm_grenade_he', 114], ['grenade.f1', 29],
            ]],

            // Двери
            ['key' => 'armored-door', 'group' => 'Двери', 'weapons' => [
                ['hatchet', 239], ['hatchet.stone', 1231], ['pickaxe.stone', 994],
                ['hammer', 75], ['rock', 471], ['flashlight.held', 821], ['torch', 1231],
                ['ammo_explosive', 250], ['grenade.beancan', 69], ['40mm_grenade_he', 36], ['satchelsthrown', 15], ['propane_bomb', 9],
                ['rocket_basic', 5], ['c4thrown', 3], ['grenade.f1', 15],
            ]],
            ['key' => 'double-wooden-door', 'group' => 'Двери', 'weapons' => [
                ['axe.salvaged', 7], ['icepick.salvaged', 9], ['hatchet', 11], ['pickaxe', 14], ['hatchet.stone', 44], ['pickaxe.stone', 103],
                ['arrow.fire', 63], ['ammo_explosive', 20], ['c4thrown', 1], ['grenade.beancan', 6], ['handmade-shell', 45], ['jackhammer', 36], ['chainsaw', 38], ['hammer', 19], ['rock', 118], ['flashlight.held', 206], ['torch', 308],
                ['flame-thrower', 103], ['rocket_basic', 1], ['torpedo', 8], ['grenade.molotov', 2], ['propane_bomb', 1], ['rocket_fire', 1], ['grenade.f1', 3],
            ]],
            ['key' => 'double-sheet-metal-door', 'group' => 'Двери', 'weapons' => [
                ['hatchet', 75], ['hatchet.stone', 385], ['pickaxe.stone', 311],
                ['hammer', 24], ['rock', 148], ['flashlight.held', 257], ['torch', 385],
                ['grenade.beancan', 18], ['satchelsthrown', 4], ['ammo_explosive', 63], ['c4thrown', 1], ['rocket_basic', 2],
                ['torpedo', 32], ['40mm_grenade_he', 9], ['propane_bomb', 3], ['grenade.f1', 4],
            ]],
            ['key' => 'sheet-metal-door', 'group' => 'Двери', 'weapons' => [
                ['hatchet', 75], ['hatchet.stone', 385], ['pickaxe.stone', 311],
                ['hammer', 24], ['rock', 148], ['flashlight.held', 257], ['torch', 385],
                ['grenade.beancan', 18], ['satchelsthrown', 4], ['ammo_explosive', 63], ['c4thrown', 1], ['rocket_basic', 2],
                ['torpedo', 32], ['40mm_grenade_he', 9], ['propane_bomb', 3], ['grenade.f1', 4],
            ]],
            ['key' => 'double-armored-door', 'group' => 'Двери', 'weapons' => [
                ['ammo_explosive', 250], ['grenade.beancan', 69], ['40mm_grenade_he', 36], ['satchelsthrown', 15], ['propane_bomb', 9],
                ['rocket_basic', 5], ['c4thrown', 3], ['grenade.f1', 15],
            ]],
            ['key' => 'garage-door', 'group' => 'Двери', 'weapons' => [
                ['hatchet', 180], ['hatchet.stone', 924], ['pickaxe.stone', 746],
                ['hammer', 56], ['rock', 356], ['flashlight.held', 616], ['torch', 924],
                ['grenade.beancan', 42], ['satchelsthrown', 9], ['ammo_explosive', 150], ['c4thrown', 2], ['rocket_basic', 3],
                ['torpedo', 75], ['propane_bomb', 5], ['40mm_grenade_he', 22], ['grenade.f1', 9],
            ]],

            // Внешние стены/ворота
            ['key' => 'high-external-wood-wall', 'group' => 'Стены', 'weapons' => [
                ['axe.salvaged', 12], ['icepick.salvaged', 15], ['hatchet', 23], ['pickaxe', 22], ['hatchet.stone', 92], ['pickaxe.stone', 191],
                ['arrow.fire', 250], ['handmade-shell', 186], ['jackhammer', 44], ['chainsaw', 70], ['hammer', 116], ['rock', 736], ['flashlight.held', 1283], ['torch', 1924],
                ['flame-thrower', 412], ['grenade.beancan', 26], ['satchelsthrown', 6], ['rocket_basic', 3], ['grenade.molotov', 7],
                ['rocket_fire', 1], ['ammo_explosive', 98], ['propane_bomb', 4], ['40mm_grenade_he', 16], ['c4thrown', 1], ['grenade.f1', 7],
            ]],
            ['key' => 'high-external-stone-wall', 'group' => 'Стены', 'weapons' => [
                ['axe.salvaged', 94], ['icepick.salvaged', 64], ['hatchet', 150], ['pickaxe', 72], ['hatchet.stone', 642], ['pickaxe.stone', 340],
                ['handmade-shell', 556], ['jackhammer', 88], ['chainsaw', 695], ['hammer', 116], ['rock', 736], ['flashlight.held', 1283], ['torch', 1924],
                ['grenade.beancan', 46], ['satchelsthrown', 10], ['rocket_basic', 4], ['grenade.molotov', 7],
                ['rocket_fire', 1], ['ammo_explosive', 185], ['propane_bomb', 7], ['40mm_grenade_he', 29], ['c4thrown', 2], ['grenade.f1', 8],
            ]],
            ['key' => 'high-external-wood-gate', 'group' => 'Стены', 'weapons' => [
                ['axe.salvaged', 12], ['icepick.salvaged', 15], ['hatchet', 23], ['pickaxe', 22], ['hatchet.stone', 92], ['pickaxe.stone', 191],
                ['arrow.fire', 250], ['handmade-shell', 186], ['jackhammer', 44], ['chainsaw', 70], ['hammer', 116], ['rock', 736], ['flashlight.held', 1283], ['torch', 1924],
                ['flame-thrower', 412], ['grenade.beancan', 26], ['satchelsthrown', 6], ['rocket_basic', 3], ['grenade.molotov', 7],
                ['rocket_fire', 1], ['ammo_explosive', 98], ['propane_bomb', 4], ['40mm_grenade_he', 16], ['c4thrown', 1], ['grenade.f1', 7],
            ]],
            ['key' => 'high-external-stone-gate', 'group' => 'Стены', 'weapons' => [
                ['axe.salvaged', 94], ['icepick.salvaged', 64], ['hatchet', 150], ['pickaxe', 72], ['hatchet.stone', 642], ['pickaxe.stone', 340],
                ['handmade-shell', 556], ['jackhammer', 88], ['chainsaw', 695], ['hammer', 116], ['rock', 736], ['flashlight.held', 1283], ['torch', 1924],
                ['grenade.beancan', 46], ['satchelsthrown', 10], ['rocket_basic', 4], ['grenade.molotov', 7],
                ['rocket_fire', 1], ['ammo_explosive', 185], ['propane_bomb', 7], ['40mm_grenade_he', 29], ['c4thrown', 2], ['grenade.f1', 8],
            ]],

            // Баррикады / окна / витрины / бойницы
            ['key' => 'barricade.wood', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 14], ['grenade.beancan', 4], ['satchelsthrown', 1], ['rocket_basic', 1], ['grenade.molotov', 1],
                ['rocket_fire', 1], ['ammo_explosive', 22], ['propane_bomb', 1], ['40mm_grenade_he', 4], ['c4thrown', 1],
            ]],
            ['key' => 'barricade.woodwire', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 23], ['grenade.beancan', 6], ['satchelsthrown', 1], ['rocket_basic', 1], ['grenade.molotov', 2],
                ['rocket_fire', 1], ['ammo_explosive', 35], ['propane_bomb', 2], ['40mm_grenade_he', 5], ['c4thrown', 1],
            ]],
            ['key' => 'window.grates.wood', 'group' => 'Двери', 'weapons' => [
                ['handmade-shell', 93], ['grenade.beancan', 13], ['satchelsthrown', 3], ['rocket_basic', 2], ['grenade.molotov', 4],
                ['rocket_fire', 1], ['ammo_explosive', 49], ['propane_bomb', 2], ['40mm_grenade_he', 8], ['c4thrown', 1],
            ]],
            ['key' => 'window.grates.metal', 'group' => 'Двери', 'weapons' => [
                ['grenade.beancan', 56], ['satchelsthrown', 12], ['rocket_basic', 4], ['ammo_explosive', 200], ['propane_bomb', 7],
                ['40mm_grenade_he', 29], ['c4thrown', 2],
            ]],
            ['key' => 'window.reinforced', 'group' => 'Двери', 'weapons' => [
                ['grenade.beancan', 56], ['satchelsthrown', 12], ['rocket_basic', 4], ['ammo_explosive', 200], ['propane_bomb', 7],
                ['40mm_grenade_he', 29], ['c4thrown', 2],
            ]],
            ['key' => 'shopfront.metal', 'group' => 'Двери', 'weapons' => [
                ['grenade.beancan', 99], ['satchelsthrown', 18], ['rocket_basic', 6], ['ammo_explosive', 300], ['propane_bomb', 10],
                ['40mm_grenade_he', 43], ['c4thrown', 3],
            ]],
            ['key' => 'embrasure.metal.vertical', 'group' => 'Двери', 'weapons' => [
                ['handmade-shell', 278], ['grenade.beancan', 59], ['satchelsthrown', 13], ['rocket_basic', 4], ['grenade.molotov', 14],
                ['rocket_fire', 2], ['ammo_explosive', 173], ['propane_bomb', 7], ['40mm_grenade_he', 28], ['c4thrown', 2],
            ]],
            ['key' => 'embrasure.metal.horizontal', 'group' => 'Двери', 'weapons' => [
                ['handmade-shell', 278], ['grenade.beancan', 59], ['satchelsthrown', 13], ['rocket_basic', 4], ['grenade.molotov', 14],
                ['rocket_fire', 2], ['ammo_explosive', 173], ['propane_bomb', 7], ['40mm_grenade_he', 28], ['c4thrown', 2],
            ]],

            // Верстаки / НПЗ / вендинг
            ['key' => 'workbench1', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 28], ['grenade.beancan', 8], ['satchelsthrown', 1], ['rocket_basic', 2], ['grenade.molotov', 2],
                ['rocket_fire', 2], ['ammo_explosive', 56], ['propane_bomb', 3], ['40mm_grenade_he', 8], ['c4thrown', 1], ['grenade.f1', 7],
            ]],
            ['key' => 'workbench2', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 278], ['grenade.beancan', 59], ['satchelsthrown', 7], ['rocket_basic', 4], ['grenade.molotov', 14],
                ['rocket_fire', 2], ['ammo_explosive', 173], ['propane_bomb', 7], ['40mm_grenade_he', 28], ['c4thrown', 1],
            ]],
            ['key' => 'workbench3', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 417], ['grenade.beancan', 89], ['satchelsthrown', 10], ['rocket_basic', 6], ['grenade.molotov', 21],
                ['rocket_fire', 2], ['ammo_explosive', 259], ['propane_bomb', 10], ['40mm_grenade_he', 42], ['c4thrown', 2],
            ]],
            ['key' => 'workbench.cook', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 28], ['grenade.beancan', 8], ['satchelsthrown', 1], ['rocket_basic', 2], ['grenade.molotov', 2],
                ['rocket_fire', 1], ['ammo_explosive', 56], ['propane_bomb', 3], ['40mm_grenade_he', 8], ['c4thrown', 1],
            ]],
            ['key' => 'workbench.engineer', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 28], ['grenade.beancan', 8], ['satchelsthrown', 1], ['rocket_basic', 2], ['grenade.molotov', 2],
                ['rocket_fire', 1], ['ammo_explosive', 56], ['propane_bomb', 3], ['40mm_grenade_he', 8], ['c4thrown', 1],
            ]],
            ['key' => 'refinery.small', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 84], ['grenade.beancan', 24], ['satchelsthrown', 6], ['rocket_basic', 5], ['grenade.molotov', 5],
                ['rocket_fire', 1], ['ammo_explosive', 167], ['propane_bomb', 8], ['40mm_grenade_he', 24], ['c4thrown', 3],
            ]],
            ['key' => 'vendingmachine', 'group' => 'Прочее', 'weapons' => [
                ['grenade.beancan', 139], ['satchelsthrown', 15], ['rocket_basic', 10], ['ammo_explosive', 449], ['propane_bomb', 17],
                ['40mm_grenade_he', 70], ['c4thrown', 3], ['grenade.f1', 22],
            ]],

            // Турели / ПВО / ловушки
            ['key' => 'auto-turret', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 56], ['grenade.beancan', 16], ['satchelsthrown', 2], ['rocket_basic', 4], ['grenade.molotov', 7],
                ['rocket_fire', 1], ['ammo_explosive', 112], ['propane_bomb', 6], ['40mm_grenade_he', 16], ['c4thrown', 1],
                ['rocket_hv', 3], ['grenade.f1', 10],
            ]],
            ['key' => 'shotgun-trap', 'group' => 'Прочее', 'weapons' => [
                ['handmade-shell', 68], ['grenade.beancan', 19], ['satchelsthrown', 2], ['rocket_basic', 5], ['grenade.molotov', 8],
                ['rocket_fire', 1], ['ammo_explosive', 134], ['propane_bomb', 7], ['40mm_grenade_he', 19], ['c4thrown', 1],
                ['rocket_hv', 4], ['grenade.f1', 12],
            ]],
            ['key' => 'sam.site', 'group' => 'Прочее', 'weapons' => [
                ['grenade.beancan', 67], ['satchelsthrown', 7], ['rocket_basic', 4], ['ammo_explosive', 112], ['40mm_grenade_he', 29],
                ['c4thrown', 1], ['rocket_hv', 23], ['grenade.f1', 134], ['propane_bomb', 7], ['grenade.molotov', 7], ['handmade-shell', 56],
                ['rifle.bolt', 125], ['rifle.ak', 200],
            ]],

            // Техника
            ['key' => 'tugboat', 'group' => 'Техника', 'weapons' => [
                ['grenade.beancan', 261], ['satchelsthrown', 2], ['rocket_basic', 16], ['ammo_explosive', 769], ['propane_bomb', 29],
                ['40mm_grenade_he', 120], ['c4thrown', 4], ['rocket_hv', 11], ['grenade.f1', 68], ['torpedo', 12],
            ]],
            ['key' => 'bradley-apc', 'group' => 'Техника', 'weapons' => [
                ['grenade.beancan', 191], ['satchelsthrown', 20], ['rocket_basic', 11], ['ammo_explosive', 571], ['40mm_grenade_he', 82],
                ['c4thrown', 3], ['rocket_hv', 7], ['grenade.f1', 40],
            ]],
            ['key' => 'minicopter', 'group' => 'Техника', 'weapons' => [
                ['grenade.beancan', 25], ['satchelsthrown', 3], ['rocket_basic', 2], ['ammo_explosive', 63], ['40mm_grenade_he', 11],
                ['c4thrown', 1], ['rocket_hv', 3], ['grenade.f1', 14], ['propane_bomb', 3], ['grenade.molotov', 11], ['rocket_heatseeker', 2],
                ['handmade-shell', 84], ['rifle.bolt', 188], ['rifle.ak', 300],
            ]],
            ['key' => 'scrap-helicopter', 'group' => 'Техника', 'weapons' => [
                ['grenade.beancan', 13], ['satchelsthrown', 2], ['rocket_basic', 2], ['ammo_explosive', 84], ['40mm_grenade_he', 8],
                ['c4thrown', 1], ['rocket_hv', 5], ['grenade.f1', 8], ['propane_bomb', 3], ['grenade.molotov', 15], ['rocket_heatseeker', 4],
                ['handmade-shell', 112], ['rifle.bolt', 250], ['rifle.ak', 400],
            ]],
            ['key' => 'attack-helicopter', 'group' => 'Техника', 'weapons' => [
                ['grenade.beancan', 29], ['satchelsthrown', 3], ['rocket_basic', 2], ['ammo_explosive', 71], ['40mm_grenade_he', 13],
                ['c4thrown', 1], ['rocket_hv', 3], ['grenade.f1', 16], ['propane_bomb', 3], ['grenade.molotov', 13], ['rocket_heatseeker', 2],
                ['handmade-shell', 95], ['rifle.bolt', 213], ['rifle.ak', 340],
            ]],

            // Прочее крупное
            ['key' => 'wind-turbine', 'group' => 'Прочее', 'weapons' => [
                ['grenade.beancan', 30], ['satchelsthrown', 7], ['rocket_basic', 2], ['ammo_explosive', 100], ['propane_bomb', 4],
                ['40mm_grenade_he', 14], ['c4thrown', 1], ['rocket_hv', 17], ['grenade.f1', 250],
            ]],

            // Тула/ТЦ и т.п.
            ['key' => 'cupboard.tool', 'group' => 'Прочее', 'weapons' => [
                ['arrow.fire', 25], ['grenade.beancan', 3], ['satchelsthrown', 1], ['ammo_explosive', 10], ['c4thrown', 1], ['handmade-shell', 23], ['jackhammer', 17], ['flame-thrower', 42], ['grenade.molotov', 1], ['grenade.f1', 2],
            ]],
        ];

        // Сборка
        $grouped = [];
        foreach ($defs as $d) {
            $weapons = [];
            foreach ($d['weapons'] as $w) {
                [$wKey, $cnt] = $w;
                $row = [
                    'key' => $wKey,
                    'name' => Statistics::getName($names, $wKey),
                    'image' => Statistics::getImageLarge($images, $wKey),
                    'count' => (int)$cnt,
                ];
                $weapons[] = $row;
            }
            $hint = $this->suggestCheaper($preferredByTarget, $d['key'], $weapons);
            $grouped[$d['group']][] = [
                'key' => $d['key'],
                'name' => Statistics::getName($names, $d['key']),
                'image' => Statistics::getImageLarge($images, $d['key']),
                'softside' => !empty($d['softside']),
                'notes' => $d['notes'] ?? null,
                'weapons' => $weapons,
                'tip' => $hint ? ('Дешевле: ' . Statistics::getName($names, $hint)) : null,
            ];
        }

        $list = [];
        foreach ($grouped as $title => $items) {
            $list[] = ['name' => Yii::t('common', $title), 'items' => $items];
        }
        return $list;
    }

    /**
     * Предложить более дешевый вариант
     */
    private function suggestCheaper(array $preferredByTarget, string $targetKey, array $weaponsRows): ?string
    {
        if (empty($preferredByTarget[$targetKey])) {
            return null;
        }
        $have = array_column($weaponsRows, 'count', 'key');
        foreach ($preferredByTarget[$targetKey] as $key) {
            if (isset($have[$key])) {
                return $key;
            }
        }
        return null;
    }
}

