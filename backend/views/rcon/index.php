<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\servers\Servers;

/** @var yii\web\View $this */
/** @var string $command */
/** @var array $results */
/** @var Servers[] $allServers */
/** @var array $selectedServers */

$this->title = Yii::t('common', 'RCON команды');
$this->params['contentClass'] = '';

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
?>

<div class="rcon-index-page w-full">
    <div class="rcon-form-layout flex flex-col min-h-0 flex-1">
        <!-- Основная колонка: как в drop/_form — без плашек и бордеров -->
        <div class="flex-1 min-w-0 p-4 lg:p-6 rcon-form-content">
            <h2 class="text-sm font-semibold text-white mb-4 uppercase tracking-wide"><?= Yii::t('common', 'Выполнить команду на серверах') ?></h2>

            <?php $form = ActiveForm::begin([
                'id' => 'rcon-form',
                'action' => ['index'],
                'method' => 'post',
                'options' => ['class' => 'rcon-form'],
            ]); ?>

            <div class="mb-4">
                <label for="command" class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'RCON команда') ?></label>
                <?= Html::textInput('command', $command, [
                    'id' => 'command',
                    'class' => 'ds-input form-control w-full',
                    'placeholder' => 'Например: serverinfo или say "Привет всем!"',
                    'required' => true,
                    'autofocus' => true,
                ]) ?>
                <p class="text-xs text-gray-500 mt-1"><?= Yii::t('common', 'Команда будет выполнена на выбранных серверах') ?></p>
            </div>

            <div class="mb-4">
                <label class="text-xs text-gray-400 mb-2 block"><?= Yii::t('common', 'Сервера') ?></label>
                <div class="flex items-center gap-3 mb-2">
                    <button type="button" class="text-blue-400 hover:text-blue-300 text-xs font-medium" id="select-all-servers"><?= Yii::t('common', 'Выбрать все') ?></button>
                    <span class="text-gray-500">|</span>
                    <button type="button" class="text-blue-400 hover:text-blue-300 text-xs font-medium" id="deselect-all-servers"><?= Yii::t('common', 'Снять все') ?></button>
                </div>
                <div class="rcon-servers-list max-h-[280px] overflow-y-auto">
                    <div class="rcon-servers-checkboxes grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
                        <?php foreach ($allServers as $server): ?>
                            <?php $isChecked = in_array($server->tag, $selectedServers); ?>
                            <label class="rcon-server-checkbox flex items-center gap-2 cursor-pointer">
                                <?= Html::checkbox('servers[]', $isChecked, [
                                    'value' => $server->tag,
                                    'id' => 'server-' . $server->id,
                                    'class' => 'server-checkbox',
                                ]) ?>
                                <span class="text-white text-sm"><?= Html::encode($server->name) ?></span>
                                <span class="text-gray-500 text-xs">(<?= Html::encode($server->tag) ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <?= Html::submitButton('<i class="fas fa-play"></i> ' . Yii::t('common', 'Выполнить'), [
                    'class' => 'ds-btn ds-btn--primary',
                    'id' => 'execute-btn',
                ]) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <?php if (!empty($results)): ?>
    <div class="rcon-results w-full mt-6">
        <div class="<?= $headerCellClass ?>"><?= Yii::t('common', 'Результаты') ?></div>
        <div class="rcon-results-body bg-[hsl(0_0%_10%_/_1)]">
            <ul class="rcon-tabs nav flex flex-wrap border-b border-[hsl(0_0%_15.3%_/_1)] px-4" role="tablist">
                <?php $firstTab = true; ?>
                <?php foreach ($results as $tag => $result): ?>
                    <?php
                    $server = $result['server'];
                    $tabId = 'rcon-tab-' . $server->id;
                    $paneId = 'rcon-pane-' . $server->id;
                    ?>
                    <li class="nav-item" role="presentation">
                        <button
                            class="rcon-tab-btn nav-link px-4 py-3 text-sm font-medium border-b-2 border-transparent -mb-px<?= $firstTab ? ' active' : '' ?>"
                            id="<?= $tabId ?>"
                            data-bs-toggle="tab"
                            data-bs-target="#<?= $paneId ?>"
                            type="button"
                            role="tab"
                            aria-controls="<?= $paneId ?>"
                            aria-selected="<?= $firstTab ? 'true' : 'false' ?>">
                            <i class="fas fa-server mr-1"></i>
                            <?= Html::encode($server->name) ?>
                            <small class="text-gray-500">(<?= Html::encode($tag) ?>)</small>
                            <?php if ($result['error']): ?>
                                <i class="fas fa-exclamation-circle text-red-400 ml-1"></i>
                            <?php elseif ($result['result'] === null): ?>
                                <i class="fas fa-exclamation-triangle text-yellow-400 ml-1"></i>
                            <?php else: ?>
                                <i class="fas fa-check-circle text-green-400 ml-1"></i>
                            <?php endif; ?>
                        </button>
                    </li>
                    <?php $firstTab = false; ?>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content rcon-tab-content p-4 min-h-[200px]">
                <?php $firstTab = true; ?>
                <?php foreach ($results as $tag => $result): ?>
                    <?php
                    $server = $result['server'];
                    $serverResult = $result['result'];
                    $error = $result['error'];
                    $paneId = 'rcon-pane-' . $server->id;

                    $isJson = false;
                    $jsonData = null;
                    $hasResultField = false;
                    if ($serverResult !== null && !empty($serverResult)) {
                        $decoded = json_decode($serverResult, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $isJson = true;
                            $jsonData = $decoded;
                            if (isset($jsonData['result']) && is_string($jsonData['result'])) {
                                $hasResultField = true;
                            }
                        }
                    }
                    ?>
                    <div class="tab-pane fade<?= $firstTab ? ' show active' : '' ?>" id="<?= $paneId ?>" role="tabpanel" aria-labelledby="<?= $tabId ?>">
                        <div class="rcon-result-item">
                            <?php if ($error): ?>
                                <div class="rcon-error flex items-center gap-2 px-4 py-3 rounded bg-red-900/30 text-red-300 text-sm">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <strong><?= Yii::t('common', 'Ошибка') ?>:</strong> <?= Html::encode($error) ?>
                                </div>
                            <?php elseif ($serverResult !== null): ?>
                                <?php if ($isJson && $hasResultField): ?>
                                    <?php $resultText = str_replace("\\n", "\n", $jsonData['result']); ?>
                                    <div class="rcon-result-content bg-[hsl(0_0%_10%_/_1)] p-4 max-h-[500px] overflow-y-auto">
                                        <pre class="m-0 font-mono text-sm text-gray-300 whitespace-pre-wrap break-words"><?= Html::encode($resultText) ?></pre>
                                    </div>
                                <?php elseif ($isJson): ?>
                                    <div class="rcon-result-json bg-[hsl(0_0%_10%_/_1)] p-4 max-h-[500px] overflow-y-auto">
                                        <pre class="m-0 font-mono text-sm text-gray-300"><code><?= Html::encode(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                    </div>
                                <?php else: ?>
                                    <?php $formattedResult = str_replace("\\n", "\n", $serverResult); ?>
                                    <div class="rcon-result-content bg-[hsl(0_0%_10%_/_1)] p-4 max-h-[500px] overflow-y-auto">
                                        <pre class="m-0 font-mono text-sm text-gray-300 whitespace-pre-wrap break-words"><?= Html::encode($formattedResult) ?></pre>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="rcon-warning flex items-center gap-2 px-4 py-3 rounded bg-yellow-900/30 text-yellow-300 text-sm">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?= Yii::t('common', 'Нет ответа от сервера') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php $firstTab = false; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.rcon-tab-btn {
    color: hsl(0 0% 70%);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
}
.rcon-tab-btn:hover { color: hsl(0 0% 90%); }
.rcon-tab-btn.active {
    color: #4a9eff;
    border-bottom-color: #4a9eff;
}
.rcon-tab-btn.active small { color: #6bb3ff; }

/* Текст результатов: явный контрастный цвет (не зависят от Tailwind) */
.rcon-result-content,
.rcon-result-json {
    background: hsl(0, 0%, 10%) !important;
    color: hsl(0, 0%, 88%) !important;
}
.rcon-result-content pre,
.rcon-result-json pre,
.rcon-result-json code {
    color: hsl(0, 0%, 88%) !important;
}
.rcon-tab-content {
    color: hsl(0, 0%, 88%);
}
.rcon-tab-content .tab-pane {
    color: inherit;
}
.rcon-result-content::-webkit-scrollbar,
.rcon-result-json::-webkit-scrollbar { width: 8px; height: 8px; }
.rcon-result-content::-webkit-scrollbar-track,
.rcon-result-json::-webkit-scrollbar-track { background: hsl(0 0% 12%); border-radius: 4px; }
.rcon-result-content::-webkit-scrollbar-thumb,
.rcon-result-json::-webkit-scrollbar-thumb { background: hsl(0 0% 25%); border-radius: 4px; }

@media (max-width: 991px) {
    .rcon-form-content { padding: 12px; }
    .rcon-servers-checkboxes { grid-template-columns: 1fr !important; }
    .rcon-server-checkbox { min-height: 44px; align-items: center; }
    .rcon-execute-btn { width: 100%; min-height: 48px; }
    /* Табы на мобилке: горизонтальная прокрутка, можно переключаться */
    .rcon-tabs {
        display: flex !important;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding: 8px 12px;
        gap: 0;
        margin: 0;
        border-bottom: 1px solid hsl(0 0% 15.3% / 1);
    }
    .rcon-tabs .nav-item {
        flex-shrink: 0;
    }
    .rcon-tabs .rcon-tab-btn {
        white-space: nowrap;
        padding: 10px 14px;
        min-height: 44px;
        font-size: 13px;
    }
    .rcon-tab-content { padding: 12px; }
    .rcon-result-content, .rcon-result-json { max-height: 320px; }
}
</style>

<script>
(function() {
    document.getElementById('select-all-servers')?.addEventListener('click', function() {
        document.querySelectorAll('.server-checkbox').forEach(function(cb) { cb.checked = true; });
    });
    document.getElementById('deselect-all-servers')?.addEventListener('click', function() {
        document.querySelectorAll('.server-checkbox').forEach(function(cb) { cb.checked = false; });
    });
})();
</script>
