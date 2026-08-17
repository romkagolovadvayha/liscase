<?php
use backend\forms\tournament\CashRaceForm;
use common\models\servers\Servers;
use common\models\tournament\Tournament;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
/** @var CashRaceForm $model */
?>
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data', 'class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full']]); ?>
<div class="flex-1 min-w-0 p-4 lg:p-6 space-y-4">
  <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] p-4">
    <h3 class="text-white text-sm uppercase tracking-wide mb-3">Описание и правила</h3>
    <?= $form->field($model, 'title')->textInput(['class' => 'ds-input form-control']) ?>
    <?= $form->field($model, 'description')->textarea(['rows' => 3, 'class' => 'ds-textarea form-control']) ?>
    <?= $form->field($model, 'rules_text')->textarea(['rows' => 8, 'class' => 'ds-textarea form-control']) ?>
  </div>
  <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] p-4">
    <h3 class="text-white text-sm uppercase tracking-wide mb-3">Механика ключей</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <?= $form->field($model, 'drop_chance')->input('number', ['step' => '0.001', 'min' => 0, 'max' => 1, 'class' => 'ds-input form-control'])->label('Шанс из бочки (0–1)') ?>
      <?= $form->field($model, 'drop_min')->input('number', ['min' => 1, 'class' => 'ds-input form-control'])->label('Минимум ключей') ?>
      <?= $form->field($model, 'drop_max')->input('number', ['min' => 1, 'class' => 'ds-input form-control'])->label('Максимум ключей') ?>
      <?= $form->field($model, 'key_shortname')->textInput(['class' => 'ds-input form-control'])->label('Rust shortname ключа') ?>
      <?= $form->field($model, 'key_skin_id')->input('number', ['min' => 0, 'class' => 'ds-input form-control'])->label('Skin ID ключа') ?>
      <?= $form->field($model, 'preview_steam_id')->textInput(['class' => 'ds-input form-control'])->label('Steam ID приватного теста') ?>
    </div>
    <?= $form->field($model, 'preview_only')->checkbox()->label('Приватный режим: только staff, серверные админы и указанный Steam ID') ?>
  </div>
  <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] p-4">
    <h3 class="text-white text-sm uppercase tracking-wide mb-3">Терминал</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <?= $form->field($model, 'terminal_active_seconds')->input('number', ['min' => 60, 'class' => 'ds-input form-control'])->label('Активен, сек.') ?>
      <?= $form->field($model, 'terminal_cooldown_min_seconds')->input('number', ['min' => 60, 'class' => 'ds-input form-control'])->label('Пауза минимум, сек.') ?>
      <?= $form->field($model, 'terminal_cooldown_max_seconds')->input('number', ['min' => 60, 'class' => 'ds-input form-control'])->label('Пауза максимум, сек.') ?>
    </div>
    <?= $form->field($model, 'terminal_prefab')->textInput(['class' => 'ds-input form-control']) ?>
    <?= $form->field($model, 'allowed_monuments_text')->textarea(['rows' => 5, 'class' => 'ds-textarea form-control'])->label('Разрешённые РТ (по одному prefab/name в строке; пусто — безопасные РТ автоматически)') ?>
  </div>
</div>
<aside class="w-full lg:w-[330px] flex-shrink-0 border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] p-4 overflow-y-auto">
  <h3 class="text-white text-sm uppercase tracking-wide mb-3">Запуск и призы</h3>
  <?= $form->field($model, 'server_id')->dropDownList(ArrayHelper::map(Servers::find()->where(['status' => Servers::STATUS_ACTIVE])->orderBy(['sort' => SORT_ASC])->all(), 'id', 'name'), ['class' => 'ds-select w-full']) ?>
  <?= $form->field($model, 'status')->dropDownList(Tournament::getStatusList(), ['class' => 'ds-select w-full']) ?>
  <?= $form->field($model, 'starts_at')->input('datetime-local', ['class' => 'ds-input form-control']) ?>
  <?= $form->field($model, 'ends_at')->input('datetime-local', ['class' => 'ds-input form-control']) ?>
  <?= $form->field($model, 'prize_pool_label')->textInput(['class' => 'ds-input form-control', 'placeholder' => '30 000 ₽']) ?>
  <?php for ($i = 0; $i < 3; $i++): ?>
    <div class="rounded border border-[hsl(0_0%_15.3%_/_1)] p-2 mb-2">
      <span class="text-xs text-gray-400"><?= $i + 1 ?> место</span>
      <?= $form->field($model, "reward_title[$i]")->textInput(['class' => 'ds-input form-control', 'placeholder' => '10 000 ₽'])->label(false) ?>
      <?= $form->field($model, "reward_subtitle[$i]")->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Золотая звезда'])->label(false) ?>
    </div>
  <?php endfor; ?>
  <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary w-full justify-center']) ?>
  <?php if (!$model->isNewRecord): ?>
    <div class="grid grid-cols-2 gap-2 mt-3">
      <?= Html::a('Запустить сейчас', ['start', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary text-center', 'data-method' => 'post']) ?>
      <?= Html::a('Завершить', ['finish', 'id' => $model->id], ['class' => 'ds-btn ds-btn--danger text-center', 'data-method' => 'post', 'data-confirm' => 'Рассчитать победителей и завершить гонку?']) ?>
    </div>
  <?php endif; ?>
</aside>
<?php ActiveForm::end(); ?>
