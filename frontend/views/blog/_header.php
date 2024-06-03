<?php

use common\models\blog\BlogCategory;
use yii\widgets\ListView;

/** @var string $title */
/** @var integer $categoryId */
/** @var \yii\data\ActiveDataProvider $dataProvider */

?>
<div class="main_header">
    <h1><?=Yii::t('database', $title)?></h1>
    <div class="main_header_data">
        <div class="main_header_data_filter">
            <div class="main_header_sorter">
                <?= ListView::widget([
                    'dataProvider' => $dataProvider,
                    'layout'       => "{sorter}",
                    'itemOptions' => [
                        'tag' => false,
                    ],
                    'options' => [
                        'tag' => false,
                    ],
                    'sorter' => [
                        'attributes' => ['views', 'created_at']
                    ],
                ]) ?>
            </div>
        </div>
        <div class="main_header_data_list">
            <a href="/rss<?=!empty($categoryId) ? "?category=$categoryId" : ''?>" class="main_header_data_list_rss" target="_blank">RSS</a>
        </div>
    </div>
</div>