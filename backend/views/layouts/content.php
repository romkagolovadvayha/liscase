<?php
/* @var $content string */

use yii\bootstrap4\Breadcrumbs;
$contentClass = isset($this->params['contentClass']) ? $this->params['contentClass'] : 'content';
?>
<div class="content-wrapper">
    <div class="<?=$contentClass?>">
        <?= $content ?>
    </div>
</div>
