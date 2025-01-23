<?php
/* @var $content string */

use yii\bootstrap4\Breadcrumbs;
$contentClass = isset($this->params['contentClass']) ? $this->params['contentClass'] : 'content';
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <?php if (isset($this->params['breadcrumbs'])): ?>
    <div class="content-header mb-2">
            <?php
            echo Breadcrumbs::widget([
                'links' => $this->params['breadcrumbs'],
                'options' => [
                    'class' => 'breadcrumb'
                ]
            ]);
            ?>
    </div>
    <?php endif; ?>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="<?=$contentClass?>">
        <?= $content ?>
    </div>
    <!-- /.content -->
</div>