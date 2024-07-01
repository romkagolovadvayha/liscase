<?php

use common\models\user\User;
use common\models\user\UserTree;
use common\models\invoice\Deposit;
use yii\web\View;
use frontend\widgets\Alert;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Партнерская программа") . " - {$user->userProfile->name}";

$serverPlay = UserTree::find()
    ->alias('ut')
    ->joinWith(['user.userProfile up'])
    ->andWhere(['ut.parent_user_id' => $user->id])
    ->andWhere(['NOT IN', 'ut.user_id', [$user->id]])
    ->andWhere(['up.parent_bonus' => 1])
    ->count();

$usersTree = UserTree::find()
    ->andWhere(['parent_user_id' => $user->id])
    ->andWhere(['NOT IN', 'user_id', [$user->id]])
    ->all();

$total = 0;
/** @var User[] $users */
foreach ($usersTree as $userTree) {
    /** @var Deposit $deposit */
    foreach ($userTree->user->deposits as $deposit) {
        if ($deposit->status !== Deposit::STATUS_SUCCESS) {
            continue;
        }
        $total += $deposit->amount;
    }
}

$dataProvider = new \yii\data\ArrayDataProvider([
                                                    'allModels' => $usersTree,
                                                    'totalCount' => count($usersTree),
                                                    'pagination' => [
                                                        'pageSize' => 10,
                                                    ],
                                                ]);
?>

<div class="container-fluid mb-5">
    <div class="main_wrap">
        <aside>
            <?= $this->render('@frontend/views/widgets/_profile'); ?>
            <?php echo $this->render('@frontend/views/layouts/_promocode_line'); ?>
        </aside>
        <main id="main" role="main">
            <div class="main_child">
                <div class="profile_content">
                    <div class="profile_content_header">
                        <?=Yii::t('common', "Партнерская программа")?>
                    </div>
                    <div class="profile_content_body">
                        <?= Alert::widget() ?>
                        <div class="referal_wrap_wrap">
                            <div class="referal_wrap">
                                <div class="referal_conditions">
                                    <div class="referal_conditions_title">
                                        <?=Yii::t('common', "Условия партнерской программы")?>
                                    </div>
                                    <div class="referal_conditions_item">
                                        <div class="referal_conditions_item_icon">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="referal_conditions_item_description">
                                            <?=Yii::t('common', "Ваш процент с донатов вам на карту")?>:
                                        </div>
                                        <div class="referal_conditions_item_title">
                                            <?=$user->userProfile->referral_bonus?>%
                                        </div>
                                    </div>
                                    <div class="referal_conditions_item">
                                        <div class="referal_conditions_item_icon">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="referal_conditions_item_description">
                                            <?=Yii::t('common', "Бонус за каждого приглашенного")?><span class="referal_conditions_item_description_color">*</span>:
                                        </div>
                                        <div class="referal_conditions_item_title">
                                            30 RUB
                                        </div>
                                    </div>
                                    <div class="referal_conditions_item">
                                        <div class="referal_conditions_item_icon">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="referal_conditions_item_description">
                                            <?=Yii::t('common', "Скин за каждого приглашенного")?><span class="referal_conditions_item_description_color">*</span>:
                                        </div>
                                        <div class="referal_conditions_item_title">
                                            от 20 до 120 RUB
                                        </div>
                                    </div>
                                </div>
                                <div class="referal_description">
                                    <span class="referal_description_color">*</span> - <?=Yii::t('common', 'Пользователь считается приглашенным в случае, если играл на любом нашем сервере более часа')?>
                                </div>
                                <div class="referal_link btn-clipboard"
                                     data-bs-toggle="tooltip"
                                     data-bs-placement="bottom"
                                     data-bs-title="<?=Yii::t('common', 'Скопировать ссылку')?>"
                                     data-clipboard-text="<?=$user->getPartnerLink()?>"
                                     data-message="<?=Yii::t('common', 'Ссылка скопирована в буфер обмена!')?>">
                                    <div class="referal_link_title">
                                        <span><?=Yii::t('common', "Ваша персональная ссылка")?></span> <i class="fas fa-copy"></i>
                                    </div>
                                    <div class="referal_link_link">
                                        <span>
                                            <?=$user->getPartnerLink()?>
                                        </span>
                                    </div>
                                </div>
                                <div class="referal_slots">
                                    <div class="referal_slots_item">
                                        <div class="referal_slots_item_title">
                                            <?=$user->userProfile->referral_click?>
                                        </div>
                                        <div class="referal_slots_item_description">
                                            <?=Yii::t('common', "Переходов по ссылке")?>
                                        </div>
                                    </div>
                                    <div class="referal_slots_item">
                                        <div class="referal_slots_item_title">
                                            <?=$user->getChildrenUserTreeQuery(1)->count()?>
                                        </div>
                                        <div class="referal_slots_item_description">
                                            <?=Yii::t('common', "Зарегистрированных")?>
                                        </div>
                                    </div>
                                    <div class="referal_slots_item">
                                        <div class="referal_slots_item_title">
                                            <?=$serverPlay?>
                                        </div>
                                        <div class="referal_slots_item_description">
                                            <?=Yii::t('common', "Поигравших более часа")?>
                                        </div>
                                    </div>
                                    <div class="referal_slots_item">
                                        <div class="referal_slots_item_title">
                                            <?= $total * ($user->userProfile->referral_bonus/100) ?> <span class="referal_slots_item_title_currency"><i class="fas fa-ruble-sign"></i></span>
                                        </div>
                                        <div class="referal_slots_item_description">
                                            <?=Yii::t('common', "Заработано")?>
                                        </div>
                                    </div>
                                    <div class="referal_slots_item">
                                        <div class="referal_slots_item_title">
                                            <?= $total * ($user->userProfile->referral_bonus/100) ?> <span class="referal_slots_item_title_currency"><i class="fas fa-ruble-sign"></i></span>
                                        </div>
                                        <div class="referal_slots_item_description">
                                            <?=Yii::t('common', "Доступно к выводу")?>
                                        </div>
                                    </div>
                                </div>
                                <div class="referal_table">
                                    <?= \kartik\grid\GridView::widget([
                                                                          'dataProvider' => $dataProvider,
                                                                          'layout'       => "{items} {pager}",
                                                                          'columns'      => [
                                                                              [
                                                                                  'attribute' => 'username',
                                                                                  'label'     => Yii::t('common', "Ник"),
                                                                                  'format'    => 'raw',
                                                                                  'value'          => function (UserTree $model) {
                                                                                      return $model->user->username;
                                                                                  },
                                                                              ],
                                                                              [
                                                                                  'attribute' => 'online',
                                                                                  'options'   => ['width' => '150'],
                                                                                  'label'     => Yii::t('common', "Более часа на сервере"),
                                                                                  'format'    => 'raw',
                                                                                  'value'          => function (UserTree $model) {
                                                                                      if ($model->user->userProfile->parent_bonus) {
                                                                                          return Yii::t('common', "Да");
                                                                                      }
                                                                                      User::parentBonus($model->user);
                                                                                      return Yii::t('common', "Нет");
                                                                                  },
                                                                              ],
                                                                              [
                                                                                  'attribute' => 'created_at',
                                                                                  'options'   => ['width' => '150'],
                                                                                  'label'     => Yii::t('common', "Дата регистрации"),
                                                                                  'format'    => 'raw',
                                                                                  'value'          => function (UserTree $model) {
                                                                                      return $model->user->created_at;
                                                                                  },
                                                                              ],
                                                                          ],
                                                                      ]);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
