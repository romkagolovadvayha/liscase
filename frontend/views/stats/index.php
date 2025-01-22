<?php

/** @var yii\web\View $this */

/** @var Servers $server */

/** @var Servers[] $servers */

use yii\bootstrap5\Html;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use yii\web\NotFoundHttpException;
use common\models\user\User;
use frontend\widgets\Alert;
use common\models\statistics\Teams;

$this->title                      = Yii::t('common', 'Статистика сервера') . ' ' . Yii::t('database', $server->name);
$this->params['meta_description'] = Yii::t('common', "Статистика игроков Rust.");
$this->params['meta_keywords']    = Yii::t('common', "стастистика игроков, статистика сервера, статистика rust");
$this->params['page']             = 'stats';

$user = Yii::$app->user->identity;

?>

<?= Alert::widget() ?>
<div class="flex flex-column gap-x-12 gap-y-12 tab-pane active" id="Max3">
    <div class="flex justify-space-between gap-x-12">
        <div class="page-stats__the-best">
            <section class="the-best__item">
                <h4 class="text-text-main mb-20 relative z-1">Лучший киллер</h4>

                <ol class="the-best__list">
                    <li>
                        <div class="inline-block">
                            <p class="flex items-center justify-space-between min-w-190 p2 mb-4">
                                <a href="">Over Prostoj</a>
                                <span>112</span>
                            </p>
                            <div
                                    class="inline-flex items-center gap-x-4 bg-background-main rounded-4 p-4"
                            >
                            <span
                                    class="icons icons_16px icons_16px_trophy bg-system-colors-gold"
                            ></span>
                                <span class="p3 text-text-main">500</span>
                                <span class="icons icons_16px icons_16px_coin"></span>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="inline-block">
                            <p class="flex items-center justify-space-between min-w-190 p2 mb-4">
                                <a href="">Over Prostoj</a>
                                <span>112</span>
                            </p>
                            <div
                                    class="inline-flex items-center gap-x-4 bg-background-main rounded-4 p-4"
                            >
                            <span
                                    class="icons icons_16px icons_16px_trophy bg-system-colors-gold"
                            ></span>
                                <span class="p3 text-text-main">500</span>
                                <span class="icons icons_16px icons_16px_coin"></span>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="inline-block">
                            <p class="flex items-center justify-space-between min-w-190 p2 mb-4">
                                <a href="">Over Prostoj</a>
                                <span>112</span>
                            </p>
                            <div
                                    class="inline-flex items-center gap-x-4 bg-background-main rounded-4 p-4"
                            >
                            <span
                                    class="icons icons_16px icons_16px_trophy bg-system-colors-gold"
                            ></span>
                                <span class="p3 text-text-main">500</span>
                                <span class="icons icons_16px icons_16px_coin"></span>
                            </div>
                        </div>
                    </li>
                </ol>
            </section>
        </div>
    </div>
</div>