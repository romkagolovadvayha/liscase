<?php
/* @var $content string */

$contentClass = isset($this->params['contentClass']) ? $this->params['contentClass'] : 'content';
$hasPageHeading = (bool) preg_match('/<h1\b/i', $content);
?>
<!-- Content Area -->
<div class="admin-content-wrapper">
    <!-- Main Content -->
    <div class="<?= $contentClass ?>">
        <?php if (!$hasPageHeading && !empty($this->title)): ?>
            <h1 class="visually-hidden"><?= \yii\helpers\Html::encode($this->title) ?></h1>
        <?php endif; ?>
        <?= $content ?>
    </div>
</div>
