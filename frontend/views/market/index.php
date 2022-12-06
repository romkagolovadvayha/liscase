<?php

use frontend\models\box\DropSearch;
use yii\data\ActiveDataProvider;
use yii\web\View;
use yii\bootstrap5\LinkPager;
use yii\bootstrap5\ActiveForm;
use common\models\box\Drop;
use common\models\box\DropType;

/** @var View $this */
/** @var DropSearch $searchModel */
/** @var ActiveDataProvider $dataProvider */
/** @var LinkPager $linkPager */

$this->title = Yii::t('common', "Маркет скинов CS GO");

$balance = 0;
if (!Yii::$app->user->isGuest) {
    $user = Yii::$app->user->identity;
    $balance = $user->getPersonalBalance()->balanceCeil;
}
$models = $dataProvider->getModels();
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
       <div class="row">
           <div class="col-md-3">
               <div class="market_filter">
                   <?php $form = ActiveForm::begin([
                           'method' => 'GET'
                   ]); ?>
                   <?= $form->field($searchModel, 'name')->textInput(); ?>
                   <?= $form->field($searchModel, 'type_id')->dropDownList(DropType::getTypeList(), [
                       'prompt' => Yii::t('common', 'Любой...'),
                   ]); ?>
                   <?= $form->field($searchModel, 'quality')->dropDownList(Drop::getQualityList(), [
                       'prompt' => Yii::t('common', 'Любой...'),
                   ]); ?>
                   <label class="form-label" for="dropsearch-price_min"><?=Yii::t('common', 'Стоимость')?></label>
                   <div class="row">
                       <div class="col-md-6">
                           <?= $form->field($searchModel, 'price_min')->label(false)->textInput(); ?>
                       </div>
                       <div class="col-md-6">
                           <?= $form->field($searchModel, 'price_max')->label(false)->textInput(); ?>
                       </div>
                   </div>
                   <button class="market_filter_btn" type="submit">Применить</button>
                   <?php ActiveForm::end(); ?>
               </div>
           </div>
           <div class="col-md-9">
               <?php if (!empty($models)): ?>
                   <div class="market_drop">
                       <?php foreach ($models as $model): ?>
                           <?= $this->render('_drop_item', [
                               'model' => $model,
                               'balance' => $balance,
                           ]); ?>
                       <?php endforeach; ?>
                   </div>
                   <div class="market_pagination">
                       <?= LinkPager::widget([
                           'pagination' => $dataProvider->pagination,
                       ])?>
                   </div>
               <?php else: ?>
                   <p><?=Yii::t('common', 'Ничего не найдено!')?></p>
               <?php endif; ?>
           </div>
       </div>
    </div>
</main>
