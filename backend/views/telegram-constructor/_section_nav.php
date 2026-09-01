<?php

use common\components\helpers\Role;
use yii\helpers\Html;

$controllerId = Yii::$app->controller->id;
$items = [
    ['id' => 'telegram-constructor', 'label' => 'Рассылки', 'icon' => 'fa-solid fa-paper-plane', 'url' => ['/telegram-constructor/index']],
    ['id' => 'telegram-constructor-message', 'label' => 'Шаблоны', 'icon' => 'fa-solid fa-message', 'url' => ['/telegram-constructor-message/index']],
];
if (Yii::$app->user->can(Role::ROLE_ADMIN)) {
    $items[] = ['id' => 'telegram-recipients', 'label' => 'Аудитории', 'icon' => 'fa-solid fa-users', 'url' => ['/telegram-recipients/index']];
}
?>
<nav class="mailing-section-nav" aria-label="Раздел рассылок">
    <?php foreach ($items as $item): ?>
        <?= Html::a(
            '<i class="' . $item['icon'] . '" aria-hidden="true"></i><span>' . Html::encode($item['label']) . '</span>',
            $item['url'],
            [
                'class' => 'mailing-section-nav__item' . ($controllerId === $item['id'] ? ' is-active' : ''),
                'aria-current' => $controllerId === $item['id'] ? 'page' : null,
            ]
        ) ?>
    <?php endforeach; ?>
</nav>
