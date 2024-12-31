<?php foreach ($templates as $template): ?>
    <div>
        <a href="<?= \yii\helpers\Url::to(['template/edit', 'id' => $template->id]) ?>"><?= $template->name ?></a>
        <a href="<?= \yii\helpers\Url::to(['template/settings', 'id' => $template->id]) ?>">Edit</a>
    </div>
<?php endforeach; ?>
