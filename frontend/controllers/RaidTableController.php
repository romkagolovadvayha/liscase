<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\statistics\Statistics;
use Yii;
use yii\web\NotFoundHttpException;

class RaidTableController extends WebController
{
    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_raid_calculator')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $this->view->title = Yii::t('common', 'Рейд таблица Rust');
        $this->view->params['page'] = 'raid-table';
        $this->view->params['meta_description'] = Yii::t(
            'common',
            'Точный калькулятор рейдов в Rust. Узнай, сколько взрывчатки нужно для стен, дверей и техники. Экономь ресурсы и планируй налёты без ошибок.'
        );
        $canonical = Yii::$app->params['homePage'] . '/raid-table';
        $this->view->registerLinkTag(['rel' => 'canonical', 'href' => $canonical]);

        $images  = Statistics::productsImages();
        $names   = Statistics::productsNames();
        $recipes = $this->recipes();                 // твои рецепты 1:1 (порох и сера считаются отдельно)
        $items   = $this->getRaidTableList($names, $images); // полный список целей/методов

        // Подключаем скрипт для автоматического открытия модального окна с промокодом
        $promoScript = $this->renderPartial('@frontend/views/layouts/_wipe-calendar-promo-script');
        
        return $this->render('table.twig', [
            'ITEMS'       => $items,
            'RECIPES'     => $this->recipes(),
            'PROD_IMAGES' => Statistics::productsImages(), // ключ -> URL
            'PROD_NAMES'  => Statistics::productsNames(),  // ключ -> имя
            'PROMO_SCRIPT' => $promoScript,
        ]);
    }

    /* ======================= РЕЦЕПТЫ (строго по твоим данным) ======================= */

    /**
     * Ключи базовых ресурсов: gunpowder, sulfur, charcoal, metal_fragments, low_grade_fuel,
     * metal_pipes, cloth, tech_trash, rope, propane_tank, small-stash, explosives.
     * ВНИМАНИЕ: порох НЕ переводим в серу — считаем отдельно (это важно!).
     */
    private function recipes(): array
    {
        return [
            // база
            'gunpowder' => [
                'yields'      => 1,
                'ingredients' => ['charcoal' => 30, 'sulfur' => 20],
            ],
            'small-stash' => [
                'yields'      => 1,
                'ingredients' => ['cloth' => 10],
            ],

            // взрывчатка (компонент)
            // из твоего «Дополнительно (Взрывчатка)» для 20 шт: GP 1000, LGF 60, S 200, MF 200
            // => на 1 шт: GP 50, LGF 3, S 10, MF 10
            'explosives' => [
                'yields'      => 1,
                'ingredients' => ['gunpowder' => 50, 'low_grade_fuel' => 3, 'sulfur' => 10, 'metal_fragments' => 10],
            ],

            // финишные предметы
            'c4thrown' => [
                'yields'      => 1,
                'ingredients' => ['explosives' => 20, 'cloth' => 5, 'tech_trash' => 2],
            ],
            'grenade.beancan' => [
                'yields'      => 1,
                'ingredients' => ['gunpowder' => 60, 'metal_fragments' => 20],
            ],
            'satchelsthrown' => [
                'yields'      => 1,
                // «Дополнительно» в твоём тексте — это раскрытие 4 бобовок и тайника.
                'ingredients' => ['grenade.beancan' => 4, 'small-stash' => 1, 'rope' => 1],
            ],
            'rocket_basic' => [
                'yields'      => 1,
                'ingredients' => ['metal_pipes' => 2, 'explosives' => 10, 'gunpowder' => 150],
            ],
            'rocket_fire' => [
                'yields'      => 1,
                'ingredients' => ['metal_pipes' => 2, 'low_grade_fuel' => 75, 'gunpowder' => 150],
            ],
            'rocket_hv' => [
                'yields'      => 1,
                'ingredients' => ['metal_pipes' => 1, 'gunpowder' => 100],
            ],
            'ammo_explosive' => [
                'yields'      => 1,
                'ingredients' => ['metal_fragments' => 10, 'gunpowder' => 20, 'sulfur' => 10],
            ],
            'grenade.molotov' => [
                'yields'      => 1,
                'ingredients' => ['cloth' => 10, 'low_grade_fuel' => 50],
            ],
            'torpedo' => [
                'yields'      => 1,
                'ingredients' => ['metal_pipes' => 1, 'gunpowder' => 30],
            ],
            'propane_bomb' => [
                'yields'      => 1,
                'ingredients' => ['propane_tank' => 1, 'gunpowder' => 450, 'low_grade_fuel' => 20],
            ],
            'grenade.f1' => [
                'yields'      => 1,
                'ingredients' => ['gunpowder' => 30, 'metal_fragments' => 25],
            ],
        ];
    }

    /* ======================= ПОМОЩНИКИ ОТОБРАЖЕНИЯ ======================= */

    private function isOverkill(string $weaponKey, string $targetKey): bool
    {
        if (in_array($targetKey, ['wood-wall', 'wooden-door', 'tool-cupboard'], true)) {
            return in_array($weaponKey, ['c4thrown', 'rocket_basic'], true);
        }
        if ($weaponKey === 'rocket_hv') return true; // HV по строениям неэффективна
        return false;
    }

    private function suggestCheaper(array $preferredByTarget, string $targetKey, array $weaponsRows): ?string
    {
        if (empty($preferredByTarget[$targetKey])) return null;
        $have = array_column($weaponsRows, 'count', 'key');
        foreach ($preferredByTarget[$targetKey] as $key) {
            if (isset($have[$key])) return $key;
        }
        return null;
    }

    private function weaponRow($names, $images, string $key, int $count): array
    {
        return [
            'key'   => $key,
            'name'  => Statistics::getName($names, $key),
            'image' => Statistics::getImage($images, $key),
            'count' => $count,
            // 'sulfur_cost' не указываем: вью считает серу на клиенте по RECIPES
        ];
    }

    /* ======================= ДАННЫЕ ДЛЯ ТАБЛИЦЫ ЦЕЛЕЙ ======================= */

    private function getRaidTableList($names, $images): array
    {
        // предпочтения «дешевле → дороже» (для подсказки)
        $preferredByTarget = [
            'wood-wall'        => ['flame-thrower','grenade.molotov','handmade-shell','grenade.beancan','satchelsthrown','ammo_explosive','rocket_basic','c4thrown'],
            'wooden-door'      => ['grenade.molotov','flame-thrower','handmade-shell','grenade.beancan','satchelsthrown','ammo_explosive','rocket_basic','c4thrown'],
            'stone-wall'       => ['satchelsthrown','rocket_basic','c4thrown','ammo_explosive','grenade.beancan'],
            'sheet-metal-door' => ['satchelsthrown','c4thrown','rocket_basic','ammo_explosive','grenade.beancan'],
            'garage-door'      => ['rocket_basic','c4thrown','ammo_explosive','satchelsthrown','grenade.beancan'],
            'armored-door'     => ['c4thrown','rocket_basic','ammo_explosive','satchelsthrown'],
            'metal-wall'       => ['rocket_basic','c4thrown','ammo_explosive','satchelsthrown'],
            'armored-wall'     => ['c4thrown','rocket_basic','ammo_explosive','satchelsthrown'],

            'high-external-wood-wall'  => ['flame-thrower','grenade.molotov','handmade-shell','grenade.beancan','satchelsthrown'],
            'high-external-stone-wall' => ['satchelsthrown','rocket_basic','c4thrown','ammo_explosive'],

            'window.grates.wood'   => ['grenade.beancan','satchelsthrown','ammo_explosive'],
            'window.grates.metal'  => ['satchelsthrown','ammo_explosive','rocket_basic'],
            'window.reinforced'    => ['satchelsthrown','ammo_explosive','rocket_basic'],
            'shopfront.metal'      => ['satchelsthrown','ammo_explosive','rocket_basic'],

            'auto-turret'  => ['rocket_basic','ammo_explosive','satchelsthrown','grenade.beancan','c4thrown'],
            'sam.site'     => ['rocket_basic','ammo_explosive','satchelsthrown'],
        ];

        // Полная матрица целей → (weaponKey, count)
        $defs = [
            // Люки
            ['key'=>'ladder-hatch','group'=>'Двери','weapons'=>[
                ['ammo_explosive',63],['c4thrown',1],['satchelsthrown',4],['rocket_basic',2],['40mm_grenade_he',9],
            ]],
            ['key'=>'triangle-hatch','group'=>'Двери','weapons'=>[
                ['ammo_explosive',63],['c4thrown',1],['satchelsthrown',4],['rocket_basic',2],['40mm_grenade_he',9],
            ]],

            // Стены
            ['key'=>'wood-wall','group'=>'Стены','softside'=>true,'weapons'=>[
                ['handmade-shell',93],['flame-thrower',206],['grenade.molotov',4],['grenade.beancan',13],['satchelsthrown',3],
                ['ammo_explosive',49],['rocket_basic',2],['rocket_fire',1],['c4thrown',1],['40mm_grenade_he',9],
            ], 'notes'=>'Огонь эффективен; C4/ракеты — оверкилл.'],
            ['key'=>'stone-wall','group'=>'Стены','softside'=>true,'weapons'=>[
                ['handmade-shell',556],['grenade.beancan',46],['satchelsthrown',10],['ammo_explosive',185],['rocket_basic',4],
                ['c4thrown',2],['40mm_grenade_he',29],
            ]],
            ['key'=>'metal-wall','group'=>'Стены','weapons'=>[
                ['grenade.beancan',112],['satchelsthrown',23],['ammo_explosive',400],['rocket_basic',8],['c4thrown',4],['40mm_grenade_he',57],
            ]],
            ['key'=>'armored-wall','group'=>'Стены','weapons'=>[
                ['grenade.beancan',223],['satchelsthrown',46],['ammo_explosive',799],['rocket_basic',15],['c4thrown',8],['40mm_grenade_he',114],
            ]],

            // Двери
            ['key'=>'armored-door','group'=>'Двери','weapons'=>[
                ['ammo_explosive',250],['grenade.beancan',69],['40mm_grenade_he',36],['satchelsthrown',15],['propane_bomb',9],
                ['rocket_basic',5],['c4thrown',3],
            ]],
            ['key'=>'double-wooden-door','group'=>'Двери','weapons'=>[
                ['ammo_explosive',20],['c4thrown',1],['grenade.beancan',6],['handmade-shell',45],['rocket_basic',1],
                ['torpedo',8],['grenade.molotov',2],['propane_bomb',1],['rocket_fire',1],
            ]],
            ['key'=>'double-sheet-metal-door','group'=>'Двери','weapons'=>[
                ['grenade.beancan',18],['satchelsthrown',4],['ammo_explosive',63],['c4thrown',1],['rocket_basic',2],
                ['torpedo',32],['40mm_grenade_he',9],['propane_bomb',3],
            ]],
            ['key'=>'sheet-metal-door','group'=>'Двери','weapons'=>[
                ['grenade.beancan',18],['satchelsthrown',4],['ammo_explosive',63],['c4thrown',1],['rocket_basic',2],
                ['torpedo',32],['40mm_grenade_he',9],['propane_bomb',3],
            ]],
            ['key'=>'double-armored-door','group'=>'Двери','weapons'=>[
                ['ammo_explosive',250],['grenade.beancan',69],['40mm_grenade_he',36],['satchelsthrown',15],['propane_bomb',9],
                ['rocket_basic',5],['c4thrown',3],
            ]],
            ['key'=>'garage-door','group'=>'Двери','weapons'=>[
                ['grenade.beancan',42],['satchelsthrown',9],['ammo_explosive',150],['c4thrown',2],['rocket_basic',3],
                ['torpedo',75],['propane_bomb',5],['40mm_grenade_he',22],
            ]],

            // Внешние стены/ворота
            ['key'=>'high-external-wood-wall','group'=>'Стены','weapons'=>[
                ['handmade-shell',186],['grenade.beancan',26],['satchelsthrown',6],['rocket_basic',3],['grenade.molotov',7],
                ['rocket_fire',1],['ammo_explosive',98],['propane_bomb',4],['40mm_grenade_he',16],['c4thrown',1],
            ]],
            ['key'=>'high-external-stone-wall','group'=>'Стены','weapons'=>[
                ['handmade-shell',556],['grenade.beancan',46],['satchelsthrown',10],['rocket_basic',4],['grenade.molotov',7],
                ['rocket_fire',1],['ammo_explosive',185],['propane_bomb',7],['40mm_grenade_he',29],['c4thrown',2],
            ]],
            ['key'=>'high-external-wood-gate','group'=>'Стены','weapons'=>[
                ['handmade-shell',186],['grenade.beancan',26],['satchelsthrown',6],['rocket_basic',3],['grenade.molotov',7],
                ['rocket_fire',1],['ammo_explosive',98],['propane_bomb',4],['40mm_grenade_he',16],['c4thrown',1],
            ]],
            ['key'=>'high-external-stone-gate','group'=>'Стены','weapons'=>[
                ['handmade-shell',556],['grenade.beancan',46],['satchelsthrown',10],['rocket_basic',4],['grenade.molotov',7],
                ['rocket_fire',1],['ammo_explosive',185],['propane_bomb',7],['40mm_grenade_he',29],['c4thrown',2],
            ]],

            // Баррикады / окна / витрины / бойницы
            ['key'=>'barricade.wood','group'=>'Прочее','weapons'=>[
                ['handmade-shell',14],['grenade.beancan',4],['satchelsthrown',1],['rocket_basic',1],['grenade.molotov',1],
                ['rocket_fire',1],['ammo_explosive',22],['propane_bomb',1],['40mm_grenade_he',4],['c4thrown',1],
            ]],
            ['key'=>'barricade.woodwire','group'=>'Прочее','weapons'=>[
                ['handmade-shell',23],['grenade.beancan',6],['satchelsthrown',1],['rocket_basic',1],['grenade.molotov',2],
                ['rocket_fire',1],['ammo_explosive',35],['propane_bomb',2],['40mm_grenade_he',5],['c4thrown',1],
            ]],
            ['key'=>'window.grates.wood','group'=>'Двери','weapons'=>[
                ['handmade-shell',93],['grenade.beancan',13],['satchelsthrown',3],['rocket_basic',2],['grenade.molotov',4],
                ['rocket_fire',1],['ammo_explosive',49],['propane_bomb',2],['40mm_grenade_he',8],['c4thrown',1],
            ]],
            ['key'=>'window.grates.metal','group'=>'Двери','weapons'=>[
                ['grenade.beancan',56],['satchelsthrown',12],['rocket_basic',4],['ammo_explosive',200],['propane_bomb',7],
                ['40mm_grenade_he',29],['c4thrown',2],
            ]],
            ['key'=>'window.reinforced','group'=>'Двери','weapons'=>[
                ['grenade.beancan',56],['satchelsthrown',12],['rocket_basic',4],['ammo_explosive',200],['propane_bomb',7],
                ['40mm_grenade_he',29],['c4thrown',2],
            ]],
            ['key'=>'shopfront.metal','group'=>'Двери','weapons'=>[
                ['grenade.beancan',99],['satchelsthrown',18],['rocket_basic',6],['ammo_explosive',300],['propane_bomb',10],
                ['40mm_grenade_he',43],['c4thrown',3],
            ]],
            ['key'=>'embrasure.metal.vertical','group'=>'Двери','weapons'=>[
                ['handmade-shell',278],['grenade.beancan',59],['satchelsthrown',13],['rocket_basic',4],['grenade.molotov',14],
                ['rocket_fire',2],['ammo_explosive',173],['propane_bomb',7],['40mm_grenade_he',28],['c4thrown',2],
            ]],
            ['key'=>'embrasure.metal.horizontal','group'=>'Двери','weapons'=>[
                ['handmade-shell',278],['grenade.beancan',59],['satchelsthrown',13],['rocket_basic',4],['grenade.molotov',14],
                ['rocket_fire',2],['ammo_explosive',173],['propane_bomb',7],['40mm_grenade_he',28],['c4thrown',2],
            ]],

            // Верстаки / НПЗ / вендинг
            ['key'=>'workbench1','group'=>'Прочее','weapons'=>[
                ['handmade-shell',28],['grenade.beancan',8],['satchelsthrown',1],['rocket_basic',2],['grenade.molotov',2],
                ['rocket_fire',2],['ammo_explosive',56],['propane_bomb',3],['40mm_grenade_he',8],['c4thrown',1],['grenade.f1',7],
            ]],
            ['key'=>'workbench2','group'=>'Прочее','weapons'=>[
                ['handmade-shell',278],['grenade.beancan',59],['satchelsthrown',7],['rocket_basic',4],['grenade.molotov',14],
                ['rocket_fire',2],['ammo_explosive',173],['propane_bomb',7],['40mm_grenade_he',28],['c4thrown',1],
            ]],
            ['key'=>'workbench3','group'=>'Прочее','weapons'=>[
                ['handmade-shell',417],['grenade.beancan',89],['satchelsthrown',10],['rocket_basic',6],['grenade.molotov',21],
                ['rocket_fire',2],['ammo_explosive',259],['propane_bomb',10],['40mm_grenade_he',42],['c4thrown',2],
            ]],
            ['key'=>'workbench.cook','group'=>'Прочее','weapons'=>[
                ['handmade-shell',28],['grenade.beancan',8],['satchelsthrown',1],['rocket_basic',2],['grenade.molotov',2],
                ['rocket_fire',1],['ammo_explosive',56],['propane_bomb',3],['40mm_grenade_he',8],['c4thrown',1],
            ]],
            ['key'=>'workbench.engineer','group'=>'Прочее','weapons'=>[
                ['handmade-shell',28],['grenade.beancan',8],['satchelsthrown',1],['rocket_basic',2],['grenade.molotov',2],
                ['rocket_fire',1],['ammo_explosive',56],['propane_bomb',3],['40mm_grenade_he',8],['c4thrown',1],
            ]],
            ['key'=>'refinery.small','group'=>'Прочее','weapons'=>[
                ['handmade-shell',84],['grenade.beancan',24],['satchelsthrown',6],['rocket_basic',5],['grenade.molotov',5],
                ['rocket_fire',1],['ammo_explosive',167],['propane_bomb',8],['40mm_grenade_he',24],['c4thrown',3],
            ]],
            ['key'=>'vendingmachine','group'=>'Прочее','weapons'=>[
                ['grenade.beancan',139],['satchelsthrown',15],['rocket_basic',10],['ammo_explosive',449],['propane_bomb',17],
                ['40mm_grenade_he',70],['c4thrown',3],
            ]],

            // Турели / ПВО
            ['key'=>'auto-turret','group'=>'Прочее','weapons'=>[
                ['handmade-shell',56],['grenade.beancan',16],['satchelsthrown',2],['rocket_basic',4],['grenade.molotov',7],
                ['rocket_fire',1],['ammo_explosive',112],['propane_bomb',6],['40mm_grenade_he',16],['c4thrown',1],
                ['rocket_hv',3],['grenade.f1',10],
            ]],
            ['key'=>'sam.site','group'=>'Прочее','weapons'=>[
                ['grenade.beancan',67],['satchelsthrown',7],['rocket_basic',4],['ammo_explosive',112],['40mm_grenade_he',29],
                ['c4thrown',1],['rocket_hv',23],['grenade.f1',134],['propane_bomb',7],['grenade.molotov',7],['handmade-shell',56],
                ['rifle.bolt',125],['rifle.ak',200],
            ]],

            // Техника
            ['key'=>'tugboat','group'=>'Техника','weapons'=>[
                ['grenade.beancan',261],['satchelsthrown',2],['rocket_basic',16],['ammo_explosive',769],['propane_bomb',29],
                ['40mm_grenade_he',120],['c4thrown',4],['rocket_hv',11],['grenade.f1',68],['torpedo',12],
            ]],
            ['key'=>'bradley-apc','group'=>'Техника','weapons'=>[
                ['grenade.beancan',191],['satchelsthrown',20],['rocket_basic',11],['ammo_explosive',571],['40mm_grenade_he',82],
                ['c4thrown',3],['rocket_hv',7],['grenade.f1',40],
            ]],
            ['key'=>'minicopter','group'=>'Техника','weapons'=>[
                ['grenade.beancan',25],['satchelsthrown',3],['rocket_basic',2],['ammo_explosive',63],['40mm_grenade_he',11],
                ['c4thrown',1],['rocket_hv',3],['grenade.f1',14],['propane_bomb',3],['grenade.molotov',11],['rocket_heatseeker',2],
                ['handmade-shell',84],['rifle.bolt',188],['rifle.ak',300],
            ]],
            ['key'=>'scrap-helicopter','group'=>'Техника','weapons'=>[
                ['grenade.beancan',13],['satchelsthrown',2],['rocket_basic',2],['ammo_explosive',84],['40mm_grenade_he',8],
                ['c4thrown',1],['rocket_hv',5],['grenade.f1',8],['propane_bomb',3],['grenade.molotov',15],['rocket_heatseeker',4],
                ['handmade-shell',112],['rifle.bolt',250],['rifle.ak',400],
            ]],
            ['key'=>'attack-helicopter','group'=>'Техника','weapons'=>[
                ['grenade.beancan',29],['satchelsthrown',3],['rocket_basic',2],['ammo_explosive',71],['40mm_grenade_he',13],
                ['c4thrown',1],['rocket_hv',3],['grenade.f1',16],['propane_bomb',3],['grenade.molotov',13],['rocket_heatseeker',2],
                ['handmade-shell',95],['rifle.bolt',213],['rifle.ak',340],
            ]],

            // Прочее крупное
            ['key'=>'wind-turbine','group'=>'Прочее','weapons'=>[
                ['grenade.beancan',30],['satchelsthrown',7],['rocket_basic',2],['ammo_explosive',100],['propane_bomb',4],
                ['40mm_grenade_he',14],['c4thrown',1],['rocket_hv',17],['grenade.f1',250],
            ]],

            // Тула/ТЦ и т.п. (по твоему раннему списку)
            ['key'=>'tool-cupboard','group'=>'Прочее','weapons'=>[
                ['grenade.beancan',3],['satchelsthrown',1],['ammo_explosive',10],['c4thrown',1],['handmade-shell',23],['flame-thrower',42],['grenade.molotov',1],
            ]],
            ['key'=>'auto-turret','group'=>'Прочее','weapons'=>[
                ['grenade.beancan',16],['satchelsthrown',2],['ammo_explosive',112],['c4thrown',1],['rocket_basic',4],['handmade-shell',56],['flame-thrower',392],['grenade.molotov',7],
            ]],
        ];

        // Сборка
        $grouped = [];
        foreach ($defs as $d) {
            $weapons = [];
            foreach ($d['weapons'] as $w) {
                [$wKey, $cnt] = $w;
                $row = $this->weaponRow($names, $images, $wKey, (int)$cnt);
                $row['overkill'] = $this->isOverkill($wKey, $d['key']);
                $weapons[] = $row;
            }
            $hint = $this->suggestCheaper($preferredByTarget, $d['key'], $weapons);
            $grouped[$d['group']][] = [
                'key'      => $d['key'],
                'name'     => Statistics::getName($names, $d['key']),
                'image'    => Statistics::getImage($images, $d['key']),
                'softside' => !empty($d['softside']),
                'notes'    => $d['notes'] ?? null,
                'weapons'  => $weapons,
                'tip'      => $hint ? ('Дешевле: ' . Statistics::getName($names, $hint)) : null,
            ];
        }

        $list = [];
        foreach ($grouped as $title => $items) {
            $list[] = ['name' => Yii::t('common', $title), 'items' => $items];
        }
        return $list;
    }
}
