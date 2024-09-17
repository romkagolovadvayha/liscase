<?php

/** @var array $data */
/** @var \common\models\invoice\Deposit $deposit */
/** @var \common\models\user\User $user */

$this->title = "Пополнения";

?>
<div class="container">
    <div class="row">
        <?php foreach ($data as $date => $_data): ?>
        <div class="col">
            <h1><?=$_data['month']?></h1>
            <ul class="list-group">
                <li class="list-group-item">Всего за месяц: <?=number_format($_data['total'], 0, '.', ' ')?> руб.</li>
                <li class="list-group-item">Потрачено на скины: <?=number_format($_data['skindrops'], 0, '.', ' ')?> руб.</li>
                <li class="list-group-item">Зарплата модератору: <?=number_format(60000, 0, '.', ' ')?> руб.</li>
                <li class="list-group-item">Сервера: <?=number_format(30000, 0, '.', ' ')?> руб.</li>
                <li class="list-group-item">Зеркалки и сайт: <?=number_format(15000, 0, '.', ' ')?> руб.</li>
                <li class="list-group-item">Итого доход: <?=number_format($_data['total']-$_data['skindrops']-60000-30000-15000, 0, '.', ' ')?> руб.</li>
                <li class="list-group-item">Человек пополнило: <?=count($_data['users'])?></li>
                <li class="list-group-item">Всего платежей: <?=count($_data['deposits'])?></li>
            </ul>
            <table class="table mt-1">
                <thead>
                <tr>
                    <th scope="col">Ник</th>
                    <th scope="col">Сумма</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($_data['users'] as $dataUser): ?>
                    <?php $user = $dataUser['user']; ?>
                    <tr>
                        <th scope="row"><?=htmlentities($user->username)?></th>
                        <td><?=number_format($dataUser['amount'], 0, '.', ' ')?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</div>
