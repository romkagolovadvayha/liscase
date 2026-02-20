<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\rustplugin\RustPluginConfig $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Конфиги плагинов Rust', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$rowClass = 'flex flex-wrap gap-2 py-3 border-b border-[hsl(0_0%_15.3%_/_1)] last:border-b-0';
$labelClass = 'text-xs text-gray-400 uppercase tracking-wide w-full md:w-32 flex-shrink-0';
$valueClass = 'text-white flex-1 min-w-0';
?>
<div class="rust-plugin-config-view w-full p-4 lg:p-6">
    <div class="max-w-4xl">
        <div class="bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
                <h1 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Html::encode($this->title) ?></h1>
            </div>
            <div class="p-4">
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>">ID</div>
                    <div class="<?= $valueClass ?>"><?= (int)$model->id ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('name') ?></div>
                    <div class="<?= $valueClass ?>"><?= Html::encode($model->name) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('created_at') ?></div>
                    <div class="<?= $valueClass ?>"><?= Yii::$app->formatter->asDatetime($model->created_at) ?></div>
                </div>
                <div class="<?= $rowClass ?>">
                    <div class="<?= $labelClass ?>"><?= $model->getAttributeLabel('updated_at') ?></div>
                    <div class="<?= $valueClass ?>"><?= Yii::$app->formatter->asDatetime($model->updated_at) ?></div>
                </div>
                <div class="pt-3">
                    <div class="text-xs text-gray-400 uppercase tracking-wide mb-2"><?= $model->getAttributeLabel('content') ?></div>
                    <div id="json-viewer" class="min-h-[500px] w-full border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden bg-[hsl(0_0%_12%_/_1)]"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js', ['defer' => true]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/mode-json.min.js', ['defer' => true]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/theme-monokai.min.js', ['defer' => true]);

$jsonContent = addslashes($model->content);
$js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    var editorDiv = document.getElementById('json-viewer');
    if (!editorDiv) return;
    
    var jsonContentStr = '{$jsonContent}';
    var jsonContent = JSON.parse(jsonContentStr);
    var formattedJson = JSON.stringify(jsonContent, null, 2);
    
    var editor = ace.edit('json-viewer');
    editor.setTheme('ace/theme/monokai');
    editor.session.setMode('ace/mode/json');
    editor.setOptions({
        fontSize: '14px',
        tabSize: 2,
        useSoftTabs: true,
        wrap: true,
        readOnly: true,
        showPrintMargin: false
    });
    
    editor.setValue(formattedJson, -1);
    editor.clearSelection();
});
JS;
$this->registerJs($js);
?>
