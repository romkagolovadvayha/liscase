<?php

use yii\web\View;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Задания на вайп") . " - {$user->userProfile->name}";
?>

<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?= $this->render('@frontend/views/widgets/_profile'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="profile_content">
                    <div class="profile_content_header">
                        <?=Yii::t('common', "Задания на вайп")?>
                    </div>
                    <div class="profile_content_body">
                        <?= Alert::widget() ?>
                        <div class="achievements_wrap">
                            <?= $this->render('blocks/_achievements'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
