<?php

use yii\web\View;
use common\models\user\UserDrop;
use common\models\box\DropBlocked;
use yii\widgets\ActiveForm;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Корзина") . " - {$user->username}";

/** @var UserDrop[] $userDrops */
$userDrops = $user->getUserDrop()
    ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
    ->orderBy(['id' => SORT_DESC])
    ->all();

/** @var \common\models\box\Category[] $categories */
$categories = \common\models\box\Category::find()
                                         ->orderBy(['sort' => SORT_ASC])
                                         ->all();

$this->registerJs(<<<JS
    var categories = $('.store_launcher_categories .store_launcher_categories_category');
    window.currentCategoryId = '';
    
    window.search = function() {
        var ul = document.getElementById("products");
        var li = ul.querySelectorAll(".store_launcher_cards_item_wrap");
        for (var i = 0; i < li.length; i++) {
            var categoryId = $(li[i]).attr('data-category-id');
            if (currentCategoryId === '' || currentCategoryId === 'all' || categoryId == currentCategoryId) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }
        }
    }
    
    categories.click(function () {
        var clickedId = $(this).attr('data-id');
        
        // Убираем active со всех
        $('.store_launcher_categories .store_launcher_categories_category').removeClass('active');
        
        // Добавляем active к нажатой
        $(this).addClass('active');
        
        // Устанавливаем текущую категорию
        window.currentCategoryId = clickedId;
        
        search();
    });
    
    // Активируем "Все" по умолчанию
    $('.store_launcher_categories_category[data-id="all"]').addClass('active');
JS
);
\frontend\assets\LauncherAsset::register($this);
?>

<?php
$lang = substr(Yii::$app->language, 0, 2);
$this->registerJs(<<<JS
        var blocked_products = $('.blocked_products_timer');
        for (var i = 0; i < blocked_products.length; i++) {
            var dateTime = $(blocked_products[i]).attr('data-time');
            var left = moment.unix(dateTime);
            $(blocked_products[i]).html(left.locale('{$lang}').fromNow());
        }
JS
);

// Стили вынесены в launcher.scss
?>
<div class="store_launcher">
    <?= Alert::widget() ?>
    
    <div class="store_launcher_header">
        <div class="store_launcher_header_left">
            <h1><?=Yii::t('common', 'Корзина сервера')?></h1>
            <p><?=Yii::t('common', 'Это ваша корзина с покупками, вы можете забрать их в любой момент')?></p>
        </div>
        <?php if (!empty($userDrops)): ?>
            <div class="store_launcher_stats">
                <div class="store_launcher_stat">
                    <div class="store_launcher_stat_icon">📦</div>
                    <div class="store_launcher_stat_content">
                        <div class="store_launcher_stat_label"><?=Yii::t('common', 'Всего предметов')?></div>
                        <div class="store_launcher_stat_value"><?=count($userDrops)?></div>
                    </div>
                </div>
                <?php if (!empty($user->server)): ?>
                    <div class="store_launcher_stat">
                        <div class="store_launcher_stat_icon">🎮</div>
                        <div class="store_launcher_stat_content">
                            <div class="store_launcher_stat_label"><?=Yii::t('common', 'Сервер')?></div>
                            <div class="store_launcher_stat_value"><?=$user->server->name?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($user->server) && (in_array($user->server->tag, ['nolimit', 'max3'])) && strtotime('2025-06-06 21:00') > time()): ?>
        <div class="content_text content_text_warning">
            🔒 <?=Yii::t('common', 'Магазин на сервере котором вы находитесь закрыт до 06.06.2025 21:00 МСК!')?>
        </div>
    <?php elseif (!empty($user->server) && ($user->server->is_store || $user->store)): ?>
        <?php if (!empty($userDrops)):?>
            <div class="store_launcher_categories_wrapper">
                <div class="store_launcher_categories">
                    <div class="store_launcher_categories_category" data-id="all">
                        <div class="store_launcher_categories_category_name"><?=Yii::t('common', 'Все')?></div>
                    </div>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($category->id === 1) continue; ?>
                        <div class="store_launcher_categories_category" data-id="<?=$category->id?>">
                            <div class="store_launcher_categories_category_name"><?=Yii::t('database', $category->name)?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="store_launcher_cards" id="products">
                <?php $serverId = $user->server->id; ?>
                <?php foreach ($userDrops as $userDrop): ?>
                    <?php $drop = Yii::$app->drop->getActiveDropById($userDrop->drop_id); ?>
                    <?=Yii::$app->view->render('_product', [
                        'drop' => $drop,
                        'serverId' => $serverId,
                        'userDrop' => $userDrop,
                    ])?>
                <?php endforeach; ?>
            </div>
        <?php else:?>
            <div class="content_text">
                📭 <?=Yii::t('common', 'В вашем инвентаре пока нет вещей')?>
            </div>
        <?php endif;?>
    <?php else: ?>
        <div class="content_text content_text_warning">
            ⚠️ <?=Yii::t('common', 'Магазин на сервере котором вы находитесь, недоступен!')?>
        </div>
    <?php endif;?>
</div>