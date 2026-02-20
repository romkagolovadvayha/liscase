<?php
/* @var $content string */

use yii\bootstrap4\Breadcrumbs;

$contentClass = isset($this->params['contentClass']) ? $this->params['contentClass'] : 'content';
?>
<!-- Content Area -->
<div class="admin-content-wrapper bg-[hsl(0_0%_10%_/_1)] min-h-full">
    <!-- Main Content -->
    <div class="<?= $contentClass ?> <?= ($contentClass === 'content-no-padding') ? '' : 'p-6' ?>">
        <?= $content ?>
    </div>
</div>
