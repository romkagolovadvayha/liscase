<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\rustplugin\RustPluginConfig $model */
/** @var yii\widgets\ActiveForm $form */

$replacementTags = [
    '{SERVER_NAME}' => 'Название сервера',
    '{SERVER_IP}' => 'IP-адрес',
    '{SERVER_PORT}' => 'Порт',
    '{SERVER_TAG}' => 'Тег сервера',
    '{SERVER_QUERY_PORT}' => 'Query-порт',
    '{SERVER_RCON_PORT}' => 'RCON-порт',
    '{SERVER_MONITORING_NAME}' => 'Название для мониторинга',
    '{SERVER_DESCRIPTION}' => 'Описание сервера',
    '{SERVER_MAP}' => 'Карта сервера',
    '{SERVER_MAX_PLAYERS}' => 'Максимальное количество игроков',
    '{SERVER_TEAM_LIMIT}' => 'Лимит команды',
    '{TEXT_IP}' => 'Текстовый IP-адрес',
    '{WIPE_TYPE}' => 'Тип вайпа',
    '{DOMAIN}' => 'Домен сайта',
];
?>

<div class="rust-plugin-config-form">
    <?= \frontend\widgets\Alert::widget() ?>
    
    <?php $form = ActiveForm::begin(); ?>
    
    <?= $form->field($model, 'name')->textInput(['maxlength' => true])->hint('Название плагина, по которому будет происходить поиск конфига в API') ?>
    
    <div class="form-group">
        <div class="control-label">Доступные теги для замены</div>
        <div class="tags-help-block">
            <p class="tags-help-block__intro">В JSON-конфиге можно использовать теги, которые API заменит значениями сервера:</p>
            <div class="tags-help-grid">
                <?php foreach ($replacementTags as $tag => $description): ?>
                    <div class="tags-help-item">
                        <button type="button" class="tag-item" data-tag="<?= Html::encode($tag) ?>" title="Скопировать <?= Html::encode($tag) ?>"><?= Html::encode($tag) ?></button>
                        <span>— <?= Html::encode($description) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="tags-help-example"><strong>Пример:</strong> <code>"host": "{SERVER_IP}:{SERVER_PORT}"</code></p>
        </div>
    </div>
    
    <div class="form-group field-rustpluginconfig-content required">
        <div class="control-label" id="content-label"><?= $model->attributeLabels()['content'] ?></div>
        <?= Html::textarea('RustPluginConfig[content]', $model->content, ['id' => 'content-hidden-input', 'rows' => 1, 'hidden' => true]) ?>
        <div id="json-editor" aria-labelledby="content-label"></div>
        <div class="help-block"></div>
    </div>
    
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
    
    <?php 
    // CSRF токены для JavaScript
    $csrfParam = Yii::$app->request->csrfParam;
    $csrfToken = Yii::$app->request->csrfToken;
    $reloadUrl = !$model->isNewRecord ? Url::to(['reload', 'id' => $model->id]) : '#';
    $configId = !$model->isNewRecord ? $model->id : null;
    ?>
    
    <?php if (!$model->isNewRecord): ?>
    <div class="plugin-reload-section">
        <h4>Перезагрузка плагина на сервере</h4>
        <p>Выберите сервер для перезагрузки плагина «<strong><?= Html::encode($model->name) ?></strong>».</p>
        
        <div class="form-group">
            <label for="reload-server-select">Сервер</label>
            <select id="reload-server-select" class="form-control ds-select plugin-reload-select">
                <option value="">Выберите сервер...</option>
                <?php
                $servers = \common\models\servers\Servers::find()
                    ->andWhere(['status' => \common\models\servers\Servers::STATUS_ACTIVE])
                    ->orderBy(['sort' => SORT_ASC])
                    ->all();
                foreach ($servers as $server): ?>
                    <option value="<?= Html::encode($server->tag) ?>">
                        <?= Html::encode($server->name) ?> (<?= Html::encode($server->tag) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="button" id="reload-plugin-btn" class="ds-btn ds-btn--primary" disabled>
            <i class="fas fa-sync-alt" aria-hidden="true"></i> Перезагрузить плагин
        </button>
        
        <div id="reload-result" class="plugin-reload-result" hidden role="status" aria-live="polite"></div>
    </div>
    <?php endif; ?>
</div>

<?php
// Ace Editor для подсветки JSON
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js', ['defer' => true]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.min.js', ['defer' => true]);

// Подготовка переменных для JavaScript
$jsConfigId = $configId ? (int)$configId : 'null';
$jsCsrfParam = json_encode($csrfParam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$jsCsrfToken = json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$jsReloadUrl = json_encode($reloadUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$js = <<<JS
(function(){
  var editor = null;

  function ensureEditor() {
    if (!editor) {
      var editorDiv = document.getElementById('json-editor');
      if (!editorDiv) return;
      
      editor = ace.edit('json-editor');
      editor.setTheme('ace/theme/monokai');
      editor.session.setMode('ace/mode/json');
      editor.session.setUseWrapMode(true);
      editor.setOptions({
        enableBasicAutocompletion: true,
        enableLiveAutocompletion: true,
        fontSize: '14px',
        tabSize: 2,
        useSoftTabs: true,
        showPrintMargin: false,
        highlightActiveLine: true,
        highlightSelectedWord: true
      });
      
      // Устанавливаем значение из скрытого input (без парсинга/форматирования для сохранения точности больших чисел)
      var hiddenInput = document.getElementById('content-hidden-input');
      var initialValue = (hiddenInput && hiddenInput.value) || '{}';
      // Устанавливаем значение как есть, без форматирования
      editor.setValue(initialValue, -1);
      editor.clearSelection();
      
      // Синхронизация со скрытым input перед отправкой формы
      var form = editorDiv.closest('form');
      if (form) {
        form.addEventListener('submit', function(e) {
          var jsonValue = editor.getValue();
          // Проверяем валидность JSON без изменения значений
          try {
            JSON.parse(jsonValue);
          } catch(err) {
            alert('Ошибка в JSON: ' + err.message);
            e.preventDefault();
            return false;
          }
          // Сохраняем исходное значение из редактора (без форматирования)
          if (hiddenInput) {
            hiddenInput.value = jsonValue;
          }
        });
      }
      
      // Также синхронизируем при изменении
      editor.on('change', function() {
        if (hiddenInput) {
          hiddenInput.value = editor.getValue();
        }
      });
      
      // Автоформатирование JSON при потере фокуса (отключено для предотвращения потери точности больших чисел)
      // Форматирование происходит только при отправке формы
    }
  }

  // Инициализация после загрузки DOM и Ace
  function init() {
    if (typeof ace === 'undefined') {
      setTimeout(init, 100);
      return;
    }
    ensureEditor();
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// Функция копирования в буфер обмена
(function(){
  function copyToClipboard(text, tagElement) {
        // Сначала пробуем современный Clipboard API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback(tagElement, text);
            }).catch(function(err) {
                console.error('Ошибка Clipboard API:', err);
                // Fallback на старый метод
                fallbackCopyToClipboard(text, tagElement);
            });
        } else {
            // Используем старый метод
            fallbackCopyToClipboard(text, tagElement);
        }
    }
    
    // Fallback метод копирования
    function fallbackCopyToClipboard(text, tagElement) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'absolute';
        textArea.style.left = '-9999px';
        textArea.style.top = (window.pageYOffset || document.documentElement.scrollTop) + 'px';
        textArea.setAttribute('readonly', '');
        textArea.setAttribute('contenteditable', 'true');
        document.body.appendChild(textArea);
        
        // Для iOS
        if (navigator.userAgent.match(/ipad|ipod|iphone/i)) {
            var range = document.createRange();
            range.selectNodeContents(textArea);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            textArea.setSelectionRange(0, text.length);
        } else {
            textArea.select();
        }
        
        var success = false;
        try {
            success = document.execCommand('copy');
        } catch (err) {
            console.error('Ошибка execCommand:', err);
        }
        
        document.body.removeChild(textArea);
        
        if (success) {
            showCopyFeedback(tagElement, text);
        } else {
            // Показываем текст для ручного копирования
            console.warn('Не удалось скопировать автоматически. Текст:', text);
            showCopyFeedback(tagElement, text);
        }
    }
    
    // Функция визуальной обратной связи
    function showCopyFeedback(tagElement, originalText) {
        tagElement.classList.add('is-copied');
        tagElement.textContent = 'Скопировано!';
        
        setTimeout(function() {
            tagElement.classList.remove('is-copied');
            tagElement.textContent = originalText;
        }, 1000);
    }
    
    // Обработка клика по тегам для копирования в буфер обмена
    var tagItems = document.querySelectorAll('.tag-item');
    if (tagItems.length > 0) {
        tagItems.forEach(function(tagElement) {
            tagElement.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var tag = this.getAttribute('data-tag') || this.textContent.trim();
                if (tag) {
                    copyToClipboard(tag, this);
                }
            });
        });
    }
    
    // Обработка перезагрузки плагина
    var reloadServerSelect = document.getElementById('reload-server-select');
    var reloadPluginBtn = document.getElementById('reload-plugin-btn');
    var reloadResult = document.getElementById('reload-result');
    
    if (reloadServerSelect && reloadPluginBtn && reloadResult) {
        // Активация кнопки при выборе сервера
        reloadServerSelect.addEventListener('change', function() {
            reloadPluginBtn.disabled = !this.value;
        });
        
        // Обработка клика по кнопке перезагрузки
        reloadPluginBtn.addEventListener('click', function() {
            var serverTag = reloadServerSelect.value;
            if (!serverTag) {
                return;
            }
            
            var configId = {$jsConfigId};
            if (!configId) {
                alert('Ошибка: ID конфига не найден');
                return;
            }
            
            // Блокируем кнопку
            reloadPluginBtn.disabled = true;
            reloadPluginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Перезагрузка...';
            reloadResult.hidden = true;
            
            // Отправляем AJAX запрос
            var formData = new FormData();
            formData.append('server_tag', serverTag);
            
            var csrfParam = {$jsCsrfParam};
            var csrfToken = {$jsCsrfToken};
            formData.append(csrfParam, csrfToken);
            
            fetch({$jsReloadUrl}, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                reloadResult.hidden = false;
                if (data.success) {
                    reloadResult.className = 'alert alert-success';
                    reloadResult.textContent = data.message || 'Плагин перезагружен';
                } else {
                    reloadResult.className = 'alert alert-danger';
                    reloadResult.textContent = data.message || 'Не удалось перезагрузить плагин';
                }
            })
            .catch(function(error) {
                reloadResult.hidden = false;
                reloadResult.className = 'alert alert-danger';
                reloadResult.textContent = 'Ошибка: ' + error.message;
            })
            .finally(function() {
                reloadPluginBtn.disabled = false;
                reloadPluginBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Перезагрузить плагин';
            });
        });
    }
})();
JS;

$this->registerJs($js);
?>

