<?php

use common\models\user\User;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\widgets\Pjax;
use yii\web\JsExpression;
use common\models\box\Drop;

/** @var yii\web\View $this */
/** @var common\models\achievements\AchievementsDaily $model */
/** @var yii\widgets\ActiveForm $form */
$searchJS = Drop::searchJS();
?>

<?php $form = ActiveForm::begin([
                                    'id' => 'AchievementsDailyFrom',
                                ]); ?>
    <?=\frontend\widgets\Alert::widget()?>
    <div class="modal-content-body">
        <?= $form->field($model, 'drop_id')->widget(\kartik\select2\Select2::class, [
            'data'    => Drop::getDropList(),
            'options' => [
                'placeholder' => 'Выберите предмет...',
                'multiple' => false,
                'debug' => true,
            ],
            'showToggleAll' => true,
            'pluginOptions' => [
                'templateResult'       => $searchJS['templateResult'],
                'templateSelection' => $searchJS['templateSelection'],
                'escapeMarkup' => $searchJS['escapeMarkup'],
                'allowClear' => true,
                'ajax' => [
                    'url' => '/drop/search-drop',
                    'dataType' => 'json',
                    'delay' => 250,
                    'data' => $searchJS['ajaxData'],
                    'processResults' => $searchJS['processResults'],
                    'cache' => true
                ],
                'debug' => true,
            ],
        ]); ?>
        <?= $form->field($model, 'amount')->textInput() ?>
    </div>
    <footer>
        <button type="submit" class="btn btn-primary">
            <span class="button__text button__dark">Сохранить</span>
        </button>
    </footer>
<?php ActiveForm::end(); ?>