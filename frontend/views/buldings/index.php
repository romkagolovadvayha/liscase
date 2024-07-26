<?php

/** @var yii\web\View $this */
/** @var \frontend\forms\promocode\PromocodeForm $promocodeForm */

use common\models\servers\Servers;

$this->title = Yii::t('common', 'Постройки на серверах Rust');

\frontend\assets\LastDropAsset::register($this);

/** @var Servers[] $servers */
$servers = Servers::find()
                  ->cache(30)
                  ->all();

?>

<div class="container-fluid mb-5">
    <div class="main_wrap server_info_page">
        <aside>
            <?= $this->render('@frontend/views/widgets/_buttons'); ?>
            <?= $this->render('@frontend/views/widgets/_servers'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
            <?= $this->render('@frontend/views/widgets/_live'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
              dsad
            </div>
        </main>
    </div>
</div>