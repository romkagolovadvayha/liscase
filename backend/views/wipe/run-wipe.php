<?php

use common\models\servers\Servers;
use yii\bootstrap5\Html;

/** @var Servers[] $servers */

$this->title = Yii::t('common', 'Выполнить вайп сервера');
?>
<div class="content-header">
    <h1><?= Html::encode($this->title) ?></h1>
</div>

<div class="content">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="ds-card mb-4">
        <div class="ds-card__header">
            <h5 class="ds-card__header-title">
                <i class="bi bi-server"></i> <?= Yii::t('common', 'Выбор сервера для вайпа') ?>
            </h5>
        </div>
        <div class="ds-card__body">
            <form method="get" action="/wipe/confirm-wipe">
                <div class="mb-3">
                    <label for="server_id" class="form-label">Выберите сервер:</label>
                    <select class="form-select" id="server_id" name="server_id" required>
                        <option value="">-- Выберите сервер --</option>
                        <?php foreach ($servers as $server): ?>
                            <option value="<?= $server->id ?>">
                                <?= Html::encode($server->name) ?> (<?= Html::encode($server->tag) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Тип вайпа:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="wipe_type" id="wipe_type_wipe" value="wipe" checked>
                        <label class="form-check-label" for="wipe_type_wipe">
                            <strong>Вайп карты</strong> (wipe) - обычный вайп карты
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="wipe_type" id="wipe_type_global" value="global">
                        <label class="form-check-label" for="wipe_type_global">
                            <strong>Глобальный вайп</strong> (global) - полный вайп сервера
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="submit" class="ds-btn ds-btn--primary">
                        <i class="bi bi-arrow-right-circle"></i> Продолжить
                    </button>
                    <?= Html::a(
                        '<i class="bi bi-arrow-left"></i> Назад',
                        '/wipe/index',
                        ['class' => 'ds-btn ds-btn--secondary']
                    ) ?>
                </div>
            </form>
        </div>
    </div>
</div>

