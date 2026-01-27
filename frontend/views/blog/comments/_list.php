<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii2mod\editable\Editable;

/* @var $this \yii\web\View */
/* @var $model \yii2mod\comments\models\CommentModel */
/* @var $maxLevel null|integer comments max level */
?>
<li class="comment" id="comment-<?php echo $model->id; ?>" itemscope itemtype="https://schema.org/Comment">
    <div class="comment-content" data-comment-content-id="<?php echo $model->id; ?>">
        <div class="comment-author-avatar">
            <?php echo Html::img($model->getAvatar(), ['alt' => $model->getAuthorName(), 'itemprop' => 'image']); ?>
        </div>
        <div class="comment-details">
            <div class="comment-action-buttons">
                <?php if (!Yii::$app->user->isGuest && ($model->level < $maxLevel || is_null($maxLevel))) : ?>
                    <?php echo Html::a("<span class='glyphicon glyphicon-share-alt'></span> " . Yii::t('common', 'Ответить'), '#', ['class' => 'reply-comment-btn', 'data' => ['action' => 'reply', 'comment-id' => $model->id]]); ?>
                <?php endif; ?>
            </div>
            <div class="">
                <span itemprop="author" itemscope itemtype="https://schema.org/Person">
                    <span itemprop="name"><?=$model->getAuthorName();?></span>
                </span>
                <?php echo Html::a($model->getPostedDate(), $model->getAnchorUrl(), [
                    'class' => 'comment-date',
                    'itemprop' => 'datePublished',
                    'datetime' => date('c', $model->createdAt)
                ]); ?>
            </div>
            <div class="comment-body" itemprop="text">
                <?php if (Yii::$app->getModule('comment')->enableInlineEdit && Yii::$app->getUser()->can('admin')): ?>
                    <?php echo Editable::widget([
                        'model' => $model,
                        'attribute' => 'content',
                        'url' => Url::to(['/comment/default/quick-edit']),
                        'options' => [
                            'id' => 'editable-comment-' . $model->id,
                        ],
                    ]); ?>
                <?php else: ?>
                    <?=Yii::t('database',  $model->getContent()); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <meta itemprop="dateCreated" content="<?= date('c', $model->createdAt) ?>">
</li>
<?php if ($model->hasChildren()) : ?>
    <ul class="children">
        <?php foreach ($model->getChildren() as $children) : ?>
            <?php echo $this->render('_list', ['model' => $children, 'maxLevel' => $maxLevel]); ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
