<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\web\JsExpression;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */
/** @var yii\widgets\ActiveForm $form */


$uploadUrl = Url::to(['/blog/tinymce-upload'], true);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
?>

<div class="blog-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput() ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>

    <?php if ($model->isNewRecord): ?>
        <p>Возможность загрузить главное изображение появится после публикации новости</p>
    <?php else: ?>
        <div class="form-group">
            <label>Главное изображение</label>
            <div>
                <a href="/blog-image/create?blogId=<?=$model->id?>" class="btn btn-primary show-modal-link"
                   data-toggl="modal"
                   data-target="modal-dialog"
                   data-title="Загрузить изображение">Загрузить главное изображение</a>
            </div>
            <?=$this->render('list-images', [
                'blogId' => $model->id,
            ])?>
        </div>
    <?php endif; ?>

    <?= $form->field($model, 'content')->widget(\dosamigos\tinymce\TinyMce::class, [
        'options' => ['rows' => 20],
        'language' => 'ru',
        'clientOptions' => [
            'plugins' => [
                'advlist','autolink','lists','link','image','media',
                'table','codesample','code','emoticons','paste','autoresize','quickbars'
            ],
            'toolbar' => 'undo redo | styles | bold italic underline | ' .
                'alignleft aligncenter alignright alignjustify | ' .
                'bullist numlist outdent indent | table | link image media | ' .
                'codesample code emoticons',
            'menubar' => 'file edit view insert format tools table',
            'statusbar' => true,
            'resize' => true,

            // ВАЖНО: НЕ указываем images_upload_url / images_upload_credentials — всё делает handler
            'images_upload_handler' => new \yii\web\JsExpression("
      (blobInfo, progress) => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '{$uploadUrl}');
        xhr.withCredentials = true; // куки/сессия для Yii2

        // прогресс, если браузер сообщает
        if (xhr.upload && typeof progress === 'function') {
          xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) progress(Math.round(e.loaded / e.total * 100));
          };
        }

        xhr.onload = () => {
          // спец-обработка 403: TinyMCE позволит удалить «битую» картинку, если вернём {remove:true}
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
        // CSRF для Yii2
        formData.append('{$csrfParam}', '{$csrfToken}');

        xhr.send(formData);
      })
    "),

            // Диалог «Вставить изображение» — тот же endpoint (используем images_upload_handler автоматически)
            'file_picker_types' => 'image',
            'file_picker_callback' => new \yii\web\JsExpression("
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
          // TinyMCE сам вызовет images_upload_handler для blob'а
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
    ]); ?>
    <?php if (!empty($model->link_name)): ?>
        <?= $form->field($model, 'link_name')->textInput() ?>
    <?php endif; ?>

    <?= $form->field($model, 'blog_category_id')->dropDownList(\common\models\blog\BlogCategory::getChildsCategories(), [
        'prompt' => Yii::t('common', 'Не выбрано...'),
    ]) ?>

    <?= $form->field($model, 'status')->dropDownList(\common\models\blog\Blog::getStatusList()) ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
