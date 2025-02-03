<?php

use yii\base\BaseObject;
use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;

/** @var common\models\blog\BlogCategory $model */

?>

<li style="list-style: none">
    <a class="button button-teritiary w-full button-size__s h-36" style="padding-top: 6px; padding-bottom: 6px;margin-top: 5px;" href="<?=$model->getUrl()?>">
        <span class="button__text"><?=Yii::t('database', $model->name)?></span>
    </a>
</li>