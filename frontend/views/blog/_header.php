<?php

use yii\widgets\ListView;

/** @var string $title */
/** @var integer $categoryId */
/** @var \yii\data\ActiveDataProvider $dataProvider */

?>
<div class="main_header_data mb-24">
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
        <a href="/rss<?=!empty($categoryId) ? "?category=$categoryId" : ''?>" class="main_header_data_list_rss" target="_blank"><i class="fas fa-rss"></i> RSS</a>
    </div>
</div>