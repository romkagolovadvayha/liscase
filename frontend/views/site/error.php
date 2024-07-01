<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\helpers\Html;

use common\models\servers\Servers;

$this->title = $name;

\frontend\assets\LastDropAsset::register($this);

?>

<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="page_content">
                    <div class="page_content_header">
                        <h1><?= Html::encode($this->title) ?></h1>
                    </div>
                    <div class="page_content_body">
                        <?= nl2br(Html::encode($message)) ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>