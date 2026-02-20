<?php

use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

/** @var yii\web\View $this */
/** @var backend\forms\blog\BlogForm|common\models\blog\Blog $model */

$uploadUrl = Url::to(['/blog/tinymce-upload'], true);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
?>

<div class="blog-form blog-form--compact flex flex-col lg:flex-row min-h-0 flex-1">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
        'id' => 'blog-form',
        'options' => ['enctype' => 'multipart/form-data', 'class' => 'flex flex-col lg:flex-row min-h-0 flex-1 w-full'],
    ]); ?>

    <!-- Основная колонка -->
    <div class="flex-1 min-w-0 p-4 lg:p-6 blog-form-content">
        <?= $form->field($model, 'name', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textInput(['class' => 'ds-input form-control']) ?>

        <?= $form->field($model, 'description', ['options' => ['class' => 'mb-2'], 'template' => '{label}{input}{error}'])->textarea(['rows' => 3, 'class' => 'ds-textarea form-control']) ?>

        <?php if ($model->isNewRecord): ?>
            <p class="text-gray-400 text-sm mb-2"><?= Yii::t('common', 'Возможность загрузить главное изображение появится после сохранения поста.') ?></p>
        <?php endif; ?>

        <?= $form->field($model, 'content', ['options' => ['class' => 'mb-2 blog-form-tinymce-wrap']])->widget(\dosamigos\tinymce\TinyMce::class, [
            'options' => ['rows' => 10, 'class' => 'blog-form-tinymce'],
            'language' => 'ru',
            'clientOptions' => [
                'skin' => 'oxide-dark',
                'content_css' => 'dark',
                'content_style' => 'body { background-color: hsl(0,0%,13%); color: #e5e5e5; }',
                'plugins' => [
                    'advlist','autolink','lists','link','image','media',
                    'table','codesample','code','emoticons','paste','autoresize','quickbars'
                ],
                'toolbar' => 'undo redo | styles | bold italic underline | ' .
                    'alignleft aligncenter alignright alignjustify | ' .
                    'bullist numlist outdent indent | table | link image media | ' .
                    'codesample code emoticons',
                'menubar' => false,
                'statusbar' => true,
                'resize' => true,
                'height' => 320,
                'min_height' => 240,
                'images_upload_handler' => new JsExpression("
      (blobInfo, progress) => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '{$uploadUrl}');
        xhr.withCredentials = true;
        if (xhr.upload && typeof progress === 'function') {
          xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) progress(Math.round(e.loaded / e.total * 100));
          };
        }
        xhr.onload = () => {
          if (xhr.status === 403) {
            reject({ message: 'HTTP Error: ' + xhr.status, remove: true });
            return;
          }
          if (xhr.status < 200 || xhr.status >= 300) {
            reject('HTTP Error: ' + xhr.status);
            return;
          }
          let json;
          try { json = JSON.parse(xhr.responseText); }
          catch (e) { reject('Invalid JSON: ' + e.message); return; }
          if (!json || typeof json.location !== 'string') {
            reject('Invalid JSON: ' + xhr.responseText);
            return;
          }
          resolve(json.location);
        };
        xhr.onerror = () => {
          reject('Image upload failed due to an XHR transport error. Code: ' + xhr.status);
        };
        const formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());
        formData.append('{$csrfParam}', '{$csrfToken}');
        xhr.send(formData);
      })
    "),
                'file_picker_types' => 'image',
                'file_picker_callback' => new JsExpression("
      (cb, value, meta) => {
        if (meta.filetype !== 'image') return;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = () => {
          const file = input.files[0];
          const blobInfo = tinymce.activeEditor.editorUpload.blobCache.create(
            file.name, file, file.size
          );
          tinymce.activeEditor.editorUpload.uploadBlob(blobInfo).then((url) => {
            cb(url, { title: file.name });
          }).catch((err) => {
            alert(err && err.message ? err.message : (''+err));
          });
        };
        input.click();
      }
    "),
                'paste_data_images' => true,
                'default_link_target' => '_blank',
                'link_context_toolbar' => true,
                'convert_urls' => false,
            ],
        ]) ?>

        <div class="mt-3">
            <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        </div>
    </div>

    <!-- Правая колонка: параметры -->
    <aside class="blog-form-sidebar admin-filters-content flex-shrink-0 w-full lg:w-[300px] lg:border-l border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)] h-full overflow-y-auto scrollbar-thin flex flex-col">
        <div class="p-4 flex-1 flex flex-col">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-white mb-3 uppercase tracking-wide"><?= Yii::t('common', 'Параметры') ?></h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('blog_category_id') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'blog_category_id', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(BlogCategory::getChildsCategories(), [
                                'class' => 'ds-select w-full text-sm',
                                'prompt' => Yii::t('common', 'Не выбрано...'),
                            ]) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= $model->getAttributeLabel('status') ?></label>
                        <div class="ds-select-wrapper">
                            <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->dropDownList(Blog::getStatusList(), ['class' => 'ds-select w-full text-sm']) ?>
                            <i class="fas fa-chevron-down ds-select-arrow"></i>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'ЧПУ') ?></label>
                        <?= $form->field($model, 'link_name', ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}'])->textInput(['class' => 'ds-input w-full text-sm form-control']) ?>
                    </div>
                    <?php if (!$model->isNewRecord): ?>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Главное изображение') ?></label>
                        <a href="<?= Url::to(['/blog-image/create', 'blogId' => $model->id]) ?>" class="ds-btn ds-btn--primary ds-btn--sm show-modal-link inline-flex items-center gap-1.5"
                           data-toggl="modal"
                           data-target="modal-dialog"
                           data-title="<?= Yii::t('common', 'Загрузить изображение') ?>"><i class="fas fa-upload"></i> <?= Yii::t('common', 'Загрузить изображение') ?></a>
                    </div>
                    <div>
                        <label class="text-xs text-gray-400 mb-1 block"><?= Yii::t('common', 'Загруженные изображения') ?>:</label>
                        <?= $this->render('list-images', ['blogId' => $model->id]) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </aside>

    <?php ActiveForm::end(); ?>
</div>
