<?php

use yii\base\BaseObject;
use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;

/** @var common\models\blog\BlogCategory $model */

?>

<li class="block_body_category_list_item">
    <a href="<?=$model->getUrl()?>" class="tm-article-title__link">
        <span><?=Yii::t('database', $model->name)?></span>
    </a>
</li>
