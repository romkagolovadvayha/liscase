<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\rustplugin\RustPluginConfig $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Конфиги плагинов Rust', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="rust-plugin-config-view">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <div>
                <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
                    'class' => 'btn btn-danger',
                    'data' => [
                        'confirm' => 'Вы уверены, что хотите удалить этот конфиг?',
                        'method' => 'post',
                    ],
                ]) ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    'name',
                    [
                        'attribute' => 'content',
                        'format' => 'raw',
                        'value' => function($model) {
                            return '<div id="json-viewer" style="height: 500px; width: 100%; border: 1px solid #ddd; border-radius: 4px;"></div>';
                        }
                    ],
                    'created_at:datetime',
                    'updated_at:datetime',
                ],
            ]) ?>
        </div>
    </div>
</div>

<?php
// Ace Editor для просмотра JSON
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

