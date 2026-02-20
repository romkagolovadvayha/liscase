<?php

use yii\bootstrap5\Html;

/**
 * @var array $rows [['server' => Servers, 'winningMap' => MapList|null, 'voteCount' => int], ...]
 */

$this->title = Yii::t('common', 'Зафиксировать карты');
$this->params['contentClass'] = 'content-no-padding';

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="fix-map-form-page w-full">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0">
                <i class="bi bi-pin-map"></i> <?= Yii::t('common', 'Карты по голосованию (не зафиксированные)') ?>
            </h2>
            <p class="text-xs text-gray-400 mt-1 mb-0">
                <?= Yii::t('common', 'Подставлена карта-победитель по голосам, не зафиксированная ни на одном сервере. Можно изменить ID. Справочно: seed и голоса.') ?>
            </p>
        </div>

        <?php $form = \yii\bootstrap5\ActiveForm::begin([
            'action' => ['/wipe/fix-map-form'],
            'method' => 'post',
            'options' => ['class' => 'fix-map-form'],
        ]); ?>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Сервер') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'ID карты') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Seed') ?></th>
                        <th class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Голосов') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $server = $row['server'];
                        $map = $row['winningMap'];
                        $voteCount = (int)($row['voteCount'] ?? 0);
                        $inputName = 'map_list_id[' . (int)$server->id . ']';
                        $inputValue = $map ? (int)$map->id : '';
                        $seedText = $map !== null ? (string)(int)$map->seed : '—';
                        ?>
                        <tr>
                            <td class="<?= $bodyCellClass ?>">
                                <span class="font-medium"><?= Html::encode($server->name) ?></span>
                                <span class="text-gray-500 text-xs block"><?= Html::encode($server->tag) ?></span>
                            </td>
                            <td class="<?= $bodyCellClass ?>">
                                <input type="text"
                                       name="<?= Html::encode($inputName) ?>"
                                       value="<?= Html::encode($inputValue) ?>"
                                       class="ds-input text-sm"
                                       style="max-width: 100px; width: 100%;"
                                       placeholder="ID"
                                       inputmode="numeric"
                                       pattern="[0-9]*">
                            </td>
                            <td class="<?= $bodyCellClass ?> text-gray-400"><?= Html::encode($seedText) ?></td>
                            <td class="<?= $bodyCellClass ?> text-gray-400"><?= $voteCount ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-[hsl(0_0%_15.3%_/_1)]">
            <?= Html::submitButton(
                '<i class="bi bi-check-lg"></i> ' . Yii::t('common', 'Сохранить'),
                ['class' => 'ds-btn ds-btn--success ds-btn--sm']
            ) ?>
        </div>

        <?php \yii\bootstrap5\ActiveForm::end(); ?>
    </div>
</div>
