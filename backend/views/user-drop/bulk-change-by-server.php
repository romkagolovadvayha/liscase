<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use common\models\servers\Servers;

$this->title = Yii::t('common', 'Массовое изменение статусов по серверу');
?>
<div class="user-drop-bulk-change-page w-full p-4 lg:p-6">
    <div class="max-w-2xl">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h1 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Html::encode($this->title) ?></h1>
            </div>
            <div class="p-4">
                <p class="text-gray-400 text-sm mb-4">
                    Выберите сервер и диапазон дат для изменения статусов предметов со статуса "Отправлен" (2) на "Доступен" (1).
                    Будут изменены только предметы пользователей, которые играли на выбранном сервере в период текущего wipe.
                </p>

                <form method="post" class="space-y-4">
                    <?= Yii::$app->request->csrfParam ? Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) : '' ?>

                    <div>
                        <label class="block text-xs text-gray-400 uppercase tracking-wide mb-1">Сервер</label>
                        <div class="ds-select-wrapper">
                            <?= Html::dropDownList(
                                'server_id',
                                $serverId ?? '',
                                ArrayHelper::merge(['' => 'Выберите сервер'], $serversList),
                                ['class' => 'ds-select form-control w-full bg-[hsl(0_0%_15%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] text-white rounded px-3 py-2', 'required' => true]
                            ) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 uppercase tracking-wide mb-1">Дата начала (sended_at)</label>
                        <?= Html::input('datetime-local', 'date_from', $dateFrom ?? '', [
                            'class' => 'form-control w-full bg-[hsl(0_0%_15%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] text-white rounded px-3 py-2',
                            'required' => true,
                        ]) ?>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-400 uppercase tracking-wide mb-1">Дата окончания (sended_at)</label>
                        <?= Html::input('datetime-local', 'date_to', $dateTo ?? '', [
                            'class' => 'form-control w-full bg-[hsl(0_0%_15%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] text-white rounded px-3 py-2',
                            'required' => true,
                        ]) ?>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <?= Html::submitButton('<i class="fas fa-list mr-1"></i> Показать список', ['class' => 'bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors']) ?>
                        <?= Html::a('Отмена', ['index'], ['class' => 'bg-[hsl(0_0%_25%_/_1)] hover:bg-[hsl(0_0%_30%_/_1)] text-white px-3 py-2 rounded text-sm font-medium transition-colors no-underline inline-flex items-center']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
