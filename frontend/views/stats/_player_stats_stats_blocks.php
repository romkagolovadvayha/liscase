<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\UserTree;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$items   = [
    [
        'name'  => Yii::t('common', 'Обломки костей'),
        'score' => Statistics::getParam($player, 'bone.fragments'),
        'image' => Statistics::getImage($images, 'bone.fragments'),
    ],
    [
        'name'  => Yii::t('common', 'Животный жир'),
        'score' => Statistics::getParam($player, 'fat.animal'),
        'image' => Statistics::getImage($images, 'fat.animal'),
    ],
    [
        'name'  => Yii::t('common', 'Кожа'),
        'score' => Statistics::getParam($player, 'leather'),
        'image' => Statistics::getImage($images, 'leather'),
    ],
    [
        'name'  => Yii::t('common', 'Прыжков с парашюта'),
        'score' => Statistics::getParam($player, 'parachuteseat'),
        'image' => Statistics::getImage($images, 'parachute'),
    ],
    [
        'name'  => Yii::t('common', 'Выкопано тайников'),
        'score' => Statistics::getParam($player, 'stash'),
        'image' => Statistics::getImage($images, 'small-stash'),
    ],
    [
        'name'  => Yii::t('common', 'Скрап'),
        'score' => Statistics::getParam($player, 'scrap'),
        'image' => Statistics::getImage($images, 'scrap'),
    ],
];

?>

<?=Yii::$app->view->render('farm_other.twig', [
    'ITEMS' => $items,
]);?>