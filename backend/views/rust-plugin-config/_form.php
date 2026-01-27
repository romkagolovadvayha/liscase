<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\rustplugin\RustPluginConfig $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="rust-plugin-config-form">
    <?= \frontend\widgets\Alert::widget() ?>
    
    <?php $form = ActiveForm::begin(); ?>
    
    <?= $form->field($model, 'name')->textInput(['maxlength' => true])->hint('Название плагина, по которому будет происходить поиск конфига в API') ?>
    
    <div class="form-group">
        <label class="control-label" style="color: #333;">Доступные теги для замены</label>
        <div class="tags-help-block" style="background: #1e1e1e; border: 1px solid #444; border-radius: 4px; padding: 15px; margin-bottom: 15px; color: #ffffff;">
            <p style="margin-bottom: 10px; font-weight: bold; color: #ffffff;">В JSON конфиге можно использовать следующие теги (будут заменены на значения сервера при запросе через API):</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 8px; font-family: monospace; font-size: 13px;">
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_NAME}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_NAME}</code> <span style="color: #d0d0d0;">- Название сервера</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_IP}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_IP}</code> <span style="color: #d0d0d0;">- IP адрес</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_PORT}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_PORT}</code> <span style="color: #d0d0d0;">- Порт</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_TAG}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_TAG}</code> <span style="color: #d0d0d0;">- Тег сервера</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_QUERY_PORT}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_QUERY_PORT}</code> <span style="color: #d0d0d0;">- Query порт</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_RCON_PORT}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_RCON_PORT}</code> <span style="color: #d0d0d0;">- RCON порт</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_MONITORING_NAME}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_MONITORING_NAME}</code> <span style="color: #d0d0d0;">- Название для мониторинга</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_DESCRIPTION}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_DESCRIPTION}</code> <span style="color: #d0d0d0;">- Описание сервера</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_MAP}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_MAP}</code> <span style="color: #d0d0d0;">- Карта сервера</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_MAX_PLAYERS}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_MAX_PLAYERS}</code> <span style="color: #d0d0d0;">- Максимальное количество игроков</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{SERVER_TEAM_LIMIT}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{SERVER_TEAM_LIMIT}</code> <span style="color: #d0d0d0;">- Лимит команды</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{TEXT_IP}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{TEXT_IP}</code> <span style="color: #d0d0d0;">- Текстовый IP адрес</span></div>
                <div style="color: #ffffff;"><code class="tag-item" data-tag="{WIPE_TYPE}" style="background: #3d3d3d; color: #61dafb; padding: 4px 8px; border-radius: 3px; cursor: pointer; user-select: none; display: inline-block; transition: all 0.2s;" title="Кликните, чтобы вставить">{WIPE_TYPE}</code> <span style="color: #d0d0d0;">- Тип вайпа (Недельный/Двухнедельный/Месячный)</span></div>
            </div>
            <p style="margin-top: 10px; margin-bottom: 0; font-size: 12px; color: #ffffff;">
                <strong style="color: #ffffff;">Пример использования:</strong> <code style="background: #3d3d3d; color: #61dafb; padding: 2px 6px; border-radius: 3px;">"host": "{SERVER_IP}:{SERVER_PORT}"</code>
            </p>
        </div>
    </div>
    
    <div class="form-group field-rustpluginconfig-content required">
        <label class="control-label" for="content-hidden-input"><?= $model->attributeLabels()['content'] ?></label>
        <?= Html::textarea('RustPluginConfig[content]', $model->content, ['id' => 'content-hidden-input', 'rows' => 1, 'style' => 'display: none;']) ?>
        <div id="json-editor" style="height: 500px; width: 100%; border: 1px solid #444; border-radius: 4px; background: #1e1e1e;"></div>
        <div class="help-block"></div>
    </div>
    
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
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
    <div class="plugin-reload-section" style="margin-top: 30px; padding: 20px; background: #1e1e1e; border: 1px solid #444; border-radius: 4px; color: #ffffff;">
        <h4 style="margin-top: 0; color: #ffffff;">Перезагрузка плагина на сервере</h4>
        <p style="color: #d0d0d0; margin-bottom: 15px;">Выберите сервер и нажмите кнопку для перезагрузки плагина "<strong style="color: #61dafb;"><?= Html::encode($model->name) ?></strong>"</p>
        
        <div class="form-group">
            <label for="reload-server-select" style="color: #ffffff;">Сервер:</label>
            <select id="reload-server-select" class="form-control" style="max-width: 400px; display: inline-block; margin-left: 10px; background: #2d2d2d; color: #ffffff; border: 1px solid #444;">
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
        
        <button type="button" id="reload-plugin-btn" class="btn btn-primary" disabled>
            <i class="fas fa-sync-alt"></i> Перезагрузить плагин
        </button>
        
        <div id="reload-result" style="margin-top: 15px; display: none;"></div>
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
        var originalBg = tagElement.style.background || '#3d3d3d';
        var originalColor = tagElement.style.color || '#61dafb';
        tagElement.style.background = '#28a745';
        tagElement.style.color = '#fff';
        tagElement.textContent = 'Скопировано!';
        
        setTimeout(function() {
            tagElement.style.background = originalBg;
            tagElement.style.color = originalColor;
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
            
            // Эффект при наведении
            tagElement.addEventListener('mouseenter', function() {
                this.style.background = '#4d4d4d';
                this.style.color = '#7dd3fc';
                this.style.transform = 'scale(1.05)';
            });
            tagElement.addEventListener('mouseleave', function() {
                this.style.background = '#3d3d3d';
                this.style.color = '#61dafb';
                this.style.transform = 'scale(1)';
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
            reloadResult.style.display = 'none';
            
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
                reloadResult.style.display = 'block';
                if (data.success) {
                    reloadResult.className = 'alert alert-success';
                    reloadResult.innerHTML = '<strong>Успешно!</strong> ' + (data.message || 'Плагин перезагружен');
                } else {
                    reloadResult.className = 'alert alert-danger';
                    reloadResult.innerHTML = '<strong>Ошибка!</strong> ' + (data.message || 'Не удалось перезагрузить плагин');
                }
            })
            .catch(function(error) {
                reloadResult.style.display = 'block';
                reloadResult.className = 'alert alert-danger';
                reloadResult.innerHTML = '<strong>Ошибка!</strong> ' + error.message;
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

