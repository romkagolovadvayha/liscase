<?php
use common\models\user\UserDrop;
use yii\web\View;

/** @var View $this */

$usersDropsLast = UserDrop::getUsersDropLast();

$result = [];
foreach ($usersDropsLast as $userDrop) {
    foreach ($userDrop->drop as $index => $drop) {
        $result[] = [
            'id' => $userDrop->id,
            'image' => $drop->imageOrig->getImagePubUrl(),
            'name' => Yii::t('database', $drop->name),
            'bgImage' => $userDrop->box->imageOrig->getImagePubUrl(),
            'bgName' => Yii::t('database', $userDrop->box->name),
            'count' => "x" . $userDrop->count,
            'userAvatar' => $userDrop->user->userProfile->avatar,
            'userName' => $userDrop->user->username,
            'type' => 0,
            'created_at' => $userDrop->created_at,
        ];
    }
}


/** @var \common\models\skindrops\Skindrops[] $skindrops */
$skindrops = \common\models\skindrops\Skindrops::find()
                                               ->limit(20)
                                               ->cache(30)
                                               ->orderBy(['id' => SORT_DESC])
                                               ->all();
foreach ($skindrops as $item) {
    /** @var \common\models\user\Auth $userAuth */
    $userAuth = \common\models\user\Auth::find()
                            ->andWhere(['source_id' => $item->steam_id])
                            ->one();
    $userAvatar = null;
    $userName = null;
    if (!empty($userAuth)) {
        $userAvatar = $userAuth->user->userProfile->avatar;
        $userName = $userAuth->user->username;
    }
    $result[] = [
        'id' => $item->id,
        'image' => $item->image,
        'name' => $item->name,
        'bgImage' => "/images/skindrops/skindrops.png",
        'bgName' => "SkinDrops",
        'count' => $item->price . " RUB",
        'userAvatar' => $userAvatar,
        'userName' => $userName,
        'type' => 1,
        'created_at' => $item->created_at,
    ];
}

usort($result, function ($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});

$result = array_slice($result, 0, 20);

$lastUserDropId = $result[0]['id'];
?>
<div class="container-fluid">
    <div class="last_drops">
        <?php foreach ($result as $item): ?>
            <?= $this->render('@frontend/views/widgets/_last_drops_item', [
                'item' => $item,
            ]); ?>
        <?php endforeach; ?>
    </div>
</div>

<?php
$this->registerJs(<<<JS
    window.lastUserDropId = {$lastUserDropId};
JS
, View::POS_HEAD);
?>