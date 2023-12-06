<?php

use common\models\user\User;
use yii\web\View;
use frontend\widgets\Alert;

/** @var yii\web\View $this */
/** @var User $user */

$this->title = $user->username . " | " . Yii::t('common', 'Профиль');
if (!empty($user->userProfile->full_name)) {
    $this->title = Yii::t('database', $user->userProfile->full_name) . " " . $this->title;
}

?>


<?= Alert::widget() ?>
<main id="main" role="main">
    <div class="content_wrapper content_wrapper_reverse container">
        <div class="side">
            <div class="side_container">
                <?=$this->render('view/_profile', ['user' => $user])?>
            </div>
        </div>
        <div class="content">
            <?=$this->render('view/_posts', ['user' => $user])?>
            <?=$this->render('view/_ratings', ['user' => $user])?>
            <?=$this->render('view/_comments', ['user' => $user])?>
        </div>
    </div>
</main>