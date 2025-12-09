<?php
use common\models\box\Drop;
use yii\widgets\Pjax;

/** @var Drop $model */
?>
<?php Pjax::begin([
    'id' => 'drop-items-pjax',
    'enablePushState' => false,
    'timeout' => 5000
]); ?>
<div class="form-group">
    <a href="/drop-drop/create?dropId=<?=$model->id?>" class="btn btn-primary show-modal-link"
       data-toggl="modal"
       data-target="modal-dialog"
       data-title="Добавить предмет">Добавить предмет</a>
</div>
<div id="drop-items-list" style="display: flex; flex-direction: column; gap: 5px;margin-top: 10px;">
    <?php foreach ($model->subDrops as $subDrop): ?>
        <div style="display: flex; gap: 10px;align-items: center;padding: 10px;border-radius: 8px;background: hsl(0, 0%, 11.8%);justify-content: space-between">
            <div style="display: flex; gap: 10px;align-items: center;">
                <img src="<?=$subDrop->drop->image()?>" width="32px"/> <?=$subDrop->drop->name?> (x<?=$subDrop->count?>)
            </div>
            <div style="display: flex; gap: 10px;align-items: center;">
                <a href="/drop-drop/delete?id=<?=$subDrop->id?>" 
                   class="delete-drop-item" 
                   style="color: red" 
                   data-id="<?=$subDrop->id?>"
                   data-pjax="0">Убрать</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php Pjax::end(); ?>

