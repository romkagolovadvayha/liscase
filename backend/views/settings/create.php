<?php
use yii\helpers\Html;
use yii\web\View;
use yii\widgets\ActiveForm;

$this->title = 'Добавить настройку';
?>

<div class="settings-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput() ?>
    <?= $form->field($model, 'category')->textInput() ?>
    <?= $form->field($model, 'type')->dropDownList([
                                                       'text' => 'Текстовое поле',
                                                       'longtext' => 'Много текста',
                                                       'color' => 'Выбор цвета',
                                                       'image' => 'Изображение',
                                                       'video' => 'Видео',
                                                       'number' => 'Числовое поле',
                                                       'checkbox' => 'Чекбокс',
                                                       'password' => 'Секретное поле (password)',
                                                   ]) ?>
    <?php if ($model->type === 'password'): ?>
        <?= $form->field($model, 'value')->passwordInput(['class' => 'form-control']) ?>
    <?php else: ?>
        <?= $form->field($model, 'value')->textInput(['class' => 'form-control']) ?>
    <?php endif; ?>
    <?= $form->field($model, 'code')->textInput() ?>
    <?= $form->field($model, 'is_translate')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
<?php
$idType = Html::getInputId($model, 'type');
$idValue = Html::getInputId($model, 'value');
$this->registerJs(<<<JS
(function () {
  var sel = document.getElementById('{$idType}');
  var val = document.getElementById('{$idValue}');
  if (!sel || !val) return;
  function sync() {
    val.type = sel.value === 'password' ? 'password' : 'text';
  }
  sel.addEventListener('change', sync);
  sync();
})();
JS
    , View::POS_READY);