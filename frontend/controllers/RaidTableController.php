<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use frontend\forms\promocode\PromocodeForm;
use Yii;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use backend\models\blog\BlogSearch;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;
use yii\web\NotFoundHttpException;
use common\components\web\Cookie;

class RaidTableController extends WebController
{
    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_raid_calculator')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $this->view->title = Yii::t('common', 'Рейд таблица Rust');
        $this->view->params['page'] = 'raid-table';
        $this->view->params['meta_description'] = Yii::t('common', "Точный калькулятор рейдов в Rust. Узнай, сколько взрывчатки нужно для стен, дверей и техники. Экономь ресурсы и планируй налёты без ошибок.");

        $images = Statistics::productsImages();
        $names = Statistics::productsNames();
        $items = $this->getRaidTableList($names, $images);

        return $this->render('table.twig', [
            'ITEMS' => $items
        ]);
    }

    private function getRaidTableList($names, $images) {
        $list = [
            // █████████████████████████████████████████████████████████
            // ███ СТЕНЫ ███████████████████████████████████████████████
            // █████████████████████████████████████████████████████████
            [
                'name' => Yii::t('common', 'Стены'),
                'items' => [
                    [
                        'name' => Statistics::getName($names, 'wood-wall'),
                        'image' => Statistics::getImage($images, 'wood-wall'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 13
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 3
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 56
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 1
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 2
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 20
                            ],
                            [
                                'name' => Statistics::getName($names, 'handmade-shell'),
                                'image' => Statistics::getImage($images, 'handmade-shell'),
                                'count' => 93
                            ],
                            [
                                'name' => Statistics::getName($names, 'flame-thrower'),
                                'image' => Statistics::getImage($images, 'flame-thrower'),
                                'count' => 196
                            ],
                            [
                                'name' => Statistics::getName($names, 'grenade.molotov'),
                                'image' => Statistics::getImage($images, 'grenade.molotov'),
                                'count' => 4
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'stone-wall'),
                        'image' => Statistics::getImage($images, 'stone-wall'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 46
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 10
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 200
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 2
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 4
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 81
                            ],
                            [
                                'name' => Statistics::getName($names, 'handmade-shell'),
                                'image' => Statistics::getImage($images, 'handmade-shell'),
                                'count' => 556
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'metal-wall'),
                        'image' => Statistics::getImage($images, 'metal-wall'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 112
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 23
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 400
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 4
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 8
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 200
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'armored-wall'),
                        'image' => Statistics::getImage($images, 'armored-wall'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 223
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 46
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 800
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 8
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 15
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 400
                            ]
                        ]
                    ]
                ]
            ],

            // █████████████████████████████████████████████████████████
            // ███ ДВЕРИ ███████████████████████████████████████████████
            // █████████████████████████████████████████████████████████
            [
                'name' => Yii::t('common', 'Двери'),
                'items' => [
                    [
                        'name' => Statistics::getName($names, 'wooden-door'),
                        'image' => Statistics::getImage($images, 'wooden-door'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 6
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 2
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 20
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 1
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 1
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 8
                            ],
                            [
                                'name' => Statistics::getName($names, 'handmade-shell'),
                                'image' => Statistics::getImage($images, 'handmade-shell'),
                                'count' => 45
                            ],
                            [
                                'name' => Statistics::getName($names, 'grenade.molotov'),
                                'image' => Statistics::getImage($images, 'grenade.molotov'),
                                'count' => 2
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'sheet-metal-door'),
                        'image' => Statistics::getImage($images, 'sheet-metal-door'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 18
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 4
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 63
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 1
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 2
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 32
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'armored-door'),
                        'image' => Statistics::getImage($images, 'armored-door'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 56
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 17
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 200
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 3
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 5
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 100
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'garage-door'),
                        'image' => Statistics::getImage($images, 'garage-door'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 42
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 9
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 150
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 2
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 3
                            ],
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 75
                            ]
                        ]
                    ]
                ]
            ],

            // █████████████████████████████████████████████████████████
            // ███ ТЕХНИКА █████████████████████████████████████████████
            // █████████████████████████████████████████████████████████
            [
                'name' => Yii::t('common', 'Техника'),
                'items' => [
                    [
                        'name' => Statistics::getName($names, 'bradley-apc'),
                        'image' => Statistics::getImage($images, 'bradley-apc'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 3
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_hv'),
                                'image' => Statistics::getImage($images, 'rocket_hv'),
                                'count' => 7
                            ],
                            [
                                'name' => Statistics::getName($names, 'grenade.f1'),
                                'image' => Statistics::getImage($images, 'grenade.f1'),
                                'count' => 40
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'patrol-helicopter'),
                        'image' => Statistics::getImage($images, 'patrol-helicopter'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'rifle.ak'),
                                'image' => Statistics::getImage($images, 'rifle.ak'),
                                'count' => 200
                            ],
                            [
                                'name' => Statistics::getName($names, 'rifle.bolt'),
                                'image' => Statistics::getImage($images, 'rifle.bolt'),
                                'count' => 134
                            ],
                            [
                                'name' => Statistics::getName($names, 'rifle.semiauto'),
                                'image' => Statistics::getImage($images, 'rifle.semiauto'),
                                'count' => 250
                            ],
                            [
                                'name' => Statistics::getName($names, 'flame-thrower'),
                                'image' => Statistics::getImage($images, 'flame-thrower'),
                                'count' => 154
                            ],
                            [
                                'name' => Statistics::getName($names, 'pistol.python'),
                                'image' => Statistics::getImage($images, 'pistol.python'),
                                'count' => 182
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'tugboat'),
                        'image' => Statistics::getImage($images, 'tugboat'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'torpedo'),
                                'image' => Statistics::getImage($images, 'torpedo'),
                                'count' => 12
                            ],
                            [
                                'name' => Statistics::getName($names, 'homing-missile'),
                                'image' => Statistics::getImage($images, 'homing-missile'),
                                'count' => 7
                            ],
                            [
                                'name' => Statistics::getName($names, 'grenade.f1'),
                                'image' => Statistics::getImage($images, 'grenade.f1'),
                                'count' => 68
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 8
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 16
                            ]
                        ]
                    ]
                ]
            ],

            // █████████████████████████████████████████████████████████
            // ███ ПРОЧЕЕ ██████████████████████████████████████████████
            // █████████████████████████████████████████████████████████
            [
                'name' => Yii::t('common', 'Прочее'),
                'items' => [
                    [
                        'name' => Statistics::getName($names, 'tool-cupboard'),
                        'image' => Statistics::getImage($images, 'tool-cupboard'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 3
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 1
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 10
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 1
                            ],
                            [
                                'name' => Statistics::getName($names, 'handmade-shell'),
                                'image' => Statistics::getImage($images, 'handmade-shell'),
                                'count' => 23
                            ],
                            [
                                'name' => Statistics::getName($names, 'flame-thrower'),
                                'image' => Statistics::getImage($images, 'flame-thrower'),
                                'count' => 42
                            ],
                            [
                                'name' => Statistics::getName($names, 'grenade.molotov'),
                                'image' => Statistics::getImage($images, 'grenade.molotov'),
                                'count' => 1
                            ]
                        ]
                    ],
                    [
                        'name' => Statistics::getName($names, 'auto-turret'),
                        'image' => Statistics::getImage($images, 'auto-turret'),
                        'weapons' => [
                            [
                                'name' => Statistics::getName($names, 'grenade.beancan'),
                                'image' => Statistics::getImage($images, 'grenade.beancan'),
                                'count' => 16
                            ],
                            [
                                'name' => Statistics::getName($names, 'satchelsthrown'),
                                'image' => Statistics::getImage($images, 'satchelsthrown'),
                                'count' => 2
                            ],
                            [
                                'name' => Statistics::getName($names, 'ammo_explosive'),
                                'image' => Statistics::getImage($images, 'ammo_explosive'),
                                'count' => 112
                            ],
                            [
                                'name' => Statistics::getName($names, 'c4thrown'),
                                'image' => Statistics::getImage($images, 'c4thrown'),
                                'count' => 1
                            ],
                            [
                                'name' => Statistics::getName($names, 'rocket_basic'),
                                'image' => Statistics::getImage($images, 'rocket_basic'),
                                'count' => 4
                            ],
                            [
                                'name' => Statistics::getName($names, 'handmade-shell'),
                                'image' => Statistics::getImage($images, 'handmade-shell'),
                                'count' => 56
                            ],
                            [
                                'name' => Statistics::getName($names, 'flame-thrower'),
                                'image' => Statistics::getImage($images, 'flame-thrower'),
                                'count' => 392
                            ],
                            [
                                'name' => Statistics::getName($names, 'grenade.molotov'),
                                'image' => Statistics::getImage($images, 'grenade.molotov'),
                                'count' => 7
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $list;
    }
}
