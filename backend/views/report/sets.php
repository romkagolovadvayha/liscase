<?php

/** @var array $data */
/** @var \common\models\box\Sets $drop */

$this->title = "Покупки";

?>
<div class="container">
    <div class="row">
        <?php foreach ($data as $date => $_data): ?>
            <div class="col">
                <h1><?=$_data['month']?></h1>
                <table class="table mt-1">
                    <thead>
                    <tr>
                        <th scope="col">Предмет</th>
                        <th scope="col">Покупок</th>
                        <th scope="col">Сумма</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($_data['products'] as $dataUser): ?>
                        <?php $drop = \common\models\box\Sets::findOne($dataUser['sets_id']); ?>
                        <tr>
                            <th scope="row"><?=$drop->name?></th>
                            <td><?=$dataUser['count']?></td>
                            <td><?=number_format($dataUser['count']*$drop->getRealPrice(), 0, '.', ' ')?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
</div>
