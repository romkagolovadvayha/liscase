<?php
use common\models\user\UserDrop;
use yii\web\View;

/** @var View $this */

$usersDropsLast = UserDrop::getUsersDropLast();
$lastUserDropId = $usersDropsLast[0]->id;
?>
<div class="container">
    <div class="last_drops">
        <?php foreach (UserDrop::getUsersDropLast() as $userDrop): ?>
            <?= $this->render('@frontend/views/widgets/_last_drops_item', [
                'userDrop' => $userDrop,
            ]); ?>
        <?php endforeach; ?>
    </div>
</div>

<?php
$this->registerJs(<<<JS
    var lastUserDropId = {$lastUserDropId};
JS
, View::POS_BEGIN);
?>