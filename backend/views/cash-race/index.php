<?php
use common\models\tournament\Tournament;
use yii\helpers\Html;
/** @var Tournament[] $models */
$this->title = 'Денежная гонка';
?>
<div class="p-4 lg:p-6">
  <div class="overflow-x-auto rounded border border-[hsl(0_0%_15.3%_/_1)]">
    <table class="table-auto w-full text-sm">
      <thead class="bg-[hsl(0_0%_20.4%_/_1)] text-gray-400"><tr><th class="p-3 text-left">Название</th><th>Сервер</th><th>Период</th><th>Фаза</th><th></th></tr></thead>
      <tbody class="text-white">
      <?php foreach ($models as $model): ?>
        <tr class="border-t border-[hsl(0_0%_15.3%_/_1)]">
          <td class="p-3"><?= Html::encode($model->title) ?><span class="block text-xs text-gray-500"><?= Html::encode($model->slug) ?></span></td>
          <td class="p-3 text-center"><?= Html::encode($model->server ? $model->server->tag : '—') ?></td>
          <td class="p-3 text-center text-xs"><?= date('d.m.Y H:i', strtotime($model->starts_at)) ?> — <?= date('d.m.Y H:i', strtotime($model->ends_at)) ?></td>
          <td class="p-3 text-center"><?= Html::encode($model->getPhaseLabel()) ?></td>
          <td class="p-3 text-right"><?= Html::a('Настроить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--secondary ds-btn--sm']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$models): ?><tr><td colspan="5" class="p-8 text-center text-gray-400">Гонки ещё не созданы.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
