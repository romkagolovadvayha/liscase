<?php
/**
 * @var \common\models\template\Template[] $templates
 * @var \common\models\template\Template|null $selectedTemplate
 * @var array $trees               // ['key' => tree]
 * @var array $rootSections        // [['key'=>..., 'label'=>...], ...]
 * @var array $debug
 */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Редактор шаблонов';

// PHP 7.4-safe
$selectedId = $selectedTemplate ? (int)$selectedTemplate->id : null;
$loadUrl    = $selectedId ? Url::to(['template/load-file',   'id' => $selectedId]) : '#';
$saveUrl    = $selectedId ? Url::to(['template/save-file',   'id' => $selectedId]) : '#';
$revertUrl  = $selectedId ? Url::to(['template/revert-file', 'id' => $selectedId]) : '#';

// CSRF → в JS
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();

// Assets: Ace, Toastr, Font Awesome
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js', ['defer' => true]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.min.js', ['defer' => true]);
$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css');

/** Рендер дерева (все папки по умолчанию свернуты) */
function renderTree(array $nodes, $rootKey = '', $prefix = '') {
    if (empty($nodes)) return;
    echo '<ul>';
    foreach ($nodes as $n) {
        if (isset($n['type']) && $n['type'] === 'dir') {
            $dirPath = $prefix . $n['name'] . '/';
            $dataPath = Html::encode($rootKey . '://' . $dirPath);
            echo '<li class="dir collapsed" data-dirpath="' . $dataPath . '"><button type="button" class="name" aria-expanded="false">📁 ' . Html::encode($n['name']) . '</button>';
            renderTree($n['children'], $rootKey, $dirPath);
            echo '</li>';
        } elseif (isset($n['type']) && $n['type'] === 'file') {
            $meta = Html::encode(json_encode([
                                                 'root' => $n['root'],
                                                 'path' => $n['path'],
                                                 'ext'  => $n['ext'],
                                             ]));
            echo '<li><button type="button" class="file" data-meta="' . $meta . '">'
                . '📄 ' . Html::encode($n['name'])
                . '<span class="dot" title="Есть оверрайд в БД"></span>'
                . '<span class="badge">'.strtoupper($n['ext']).'</span>'
                . '</button></li>';
        }
    }
    echo '</ul>';
}
?>

<div class="template-editor-page">
    <h1><?= Html::encode($this->title) ?></h1>

<?php if (!empty($debug)): ?>
    <div class="template-editor-debug">
        <strong>Диагностика путей</strong>
        <ul>
            <?php foreach ($debug as $d): ?>
                <li>
                    <code><?= Html::encode($d['alias']) ?></code> →
                    <code><?= Html::encode($d['path']) ?></code>,
                    существует: <strong><?= Html::encode($d['exists']) ?></strong>,
                    узлов верхнего уровня: <?= (int)$d['count'] ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

    <div class="template-editor-selector">
        <div><strong>Текущий шаблон:</strong></div>
        <form method="get" action="<?= Url::to(['template/index']) ?>">
            <label class="visually-hidden" for="template-id">Выберите шаблон</label>
            <select id="template-id" name="templateId" class="ds-select form-control" onchange="this.form.submit()">
                <?php foreach ($templates as $t): ?>
                    <option value="<?= (int)$t->id ?>" <?= ($selectedTemplate && $t->id === $selectedTemplate->id) ? 'selected' : '' ?>>
                        #<?= (int)$t->id ?> — <?= Html::encode($t->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="ms-auto">
            <a class="ds-btn ds-btn--secondary ds-btn--sm" href="<?= $selectedId ? Url::to(['template/settings', 'id' => $selectedId]) : '#' ?>">Настройки шаблона</a>
        </div>
    </div>

    <div class="editor-wrap">
        <div class="left">
            <div class="tree" id="tree">
                <?php foreach ($rootSections as $section): ?>
                    <h3><?= Html::encode($section['label']) ?></h3>
                    <?php
                    $key = $section['key'];
                    renderTree(isset($trees[$key]) ? $trees[$key] : [], $key, '');
                    ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pane">
            <div class="toolbar">
                <div>
                    <div><strong id="fileLabel">Файл не выбран</strong></div>
                    <div id="filePath" class="template-editor-path"></div>
                </div>
                <div class="status">Источник: <span id="sourceBadge">—</span></div>
            </div>
            <div id="editor">// Выберите файл слева…</div>
            <div class="template-editor-actions">
                <button type="button" id="saveBtn" class="ds-btn ds-btn--primary" disabled>Сохранить в БД</button>
                <button type="button" id="revertBtn" class="ds-btn ds-btn--danger" disabled>Удалить переопределение</button>
            </div>
        </div>
    </div>
</div>

    <script>
        // CSRF → JS
        window.__csrfParam = <?= json_encode($csrfParam) ?>;
        window.__csrfToken = <?= json_encode($csrfToken) ?>;
    </script>

<?php
// JS (без бэктиков, PHP 7.4-safe)
$js = <<<JS
(function(){
  var editor = null;
  var current = {root:null, path:null, ext:null};

  // toastr helpers
  function toastSuccess(msg){
    try { toastr.success(String(msg || 'Готово'), '', {progressBar:true, positionClass:'toast-top-right', escapeHtml:true}); } catch(e) {}
  }
  function toastError(msg){
    try { toastr.error(String(msg || 'Ошибка'), '', {progressBar:true, positionClass:'toast-top-right', escapeHtml:true}); } catch(e) {}
  }

  // localStorage keys per template
  var LS_PREFIX = 'tplEditor:' + ('{$selectedId}' || 'none') + ':';
  var LS_OPEN_FILE  = LS_PREFIX + 'openFile';
  var LS_EXPANDED   = LS_PREFIX + 'expandedDirs';
  var LS_TREE_SCROLL= LS_PREFIX + 'treeScrollTop';

  function ensureEditor() {
    if (!editor) {
      editor = ace.edit('editor');
      editor.setTheme('ace/theme/monokai');
      editor.session.setUseWrapMode(true);
      editor.setOptions({
        enableBasicAutocompletion: true,
        enableLiveAutocompletion: true,
        fontSize: '14px',
        tabSize: 2,
        useSoftTabs: true
      });
    }
  }

  function setMode(ext) {
    var map = { php:'ace/mode/php', twig:'ace/mode/twig', scss:'ace/mode/scss', js:'ace/mode/javascript' };
    var mode = map[ext] ? map[ext] : 'ace/mode/text';
    editor.session.setMode(mode);
  }

  function setToolbar(meta, from) {
    var fileLabel = document.getElementById('fileLabel');
    var filePath  = document.getElementById('filePath');
    var badge     = document.getElementById('sourceBadge');

    fileLabel.textContent = meta ? meta.path.split('/').pop() : 'Файл не выбран';
    filePath.textContent  = meta ? (meta.root + '://' + meta.path) : '';
    badge.textContent     = from ? ((from === 'db') ? 'Переопределение из БД' : 'Файл на диске') : '—';
  }

  function setButtons(enabled) {
    document.getElementById('saveBtn').disabled = !enabled;
    document.getElementById('revertBtn').disabled = !enabled;
  }

  function markDotForPath(path, show) {
    var files = document.querySelectorAll('.tree .file');
    for (var i=0; i<files.length; i++) {
      var metaStr = files[i].getAttribute('data-meta');
      try {
        var meta = JSON.parse(metaStr);
        if (meta && meta.path === path) {
          var dot = files[i].querySelector('.dot');
          if (dot) dot.style.display = show ? 'inline-block' : 'none';
        }
      } catch(e) {}
    }
  }

  function clearAllDots() {
    var dots = document.querySelectorAll('.tree .file .dot');
    for (var i=0; i<dots.length; i++) dots[i].style.display = 'none';
  }

  function parseJsonSafe(res) {
    var ct = res.headers.get('content-type') || '';
    if (ct.indexOf('application/json') !== -1) return res.json();
    return res.text().then(function(t){
      throw new Error(t ? t.substring(0, 200) : 'Сервер вернул некорректный ответ');
    });
  }

  function saveOpenFile(meta) {
    try { localStorage.setItem(LS_OPEN_FILE, JSON.stringify(meta)); } catch(e) {}
  }
  function getSavedOpenFile() {
    try { var v = localStorage.getItem(LS_OPEN_FILE); return v ? JSON.parse(v) : null; } catch(e) { return null; }
  }

  function getExpandedSet() {
    try {
      var raw = localStorage.getItem(LS_EXPANDED);
      var arr = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(arr)) arr = [];
      var set = {};
      for (var i=0;i<arr.length;i++) set[arr[i]] = true;
      return set;
    } catch(e) { return {}; }
  }
  function saveExpandedSet(setObj) {
    try {
      var arr = [];
      for (var k in setObj) if (setObj.hasOwnProperty(k) && setObj[k]) arr.push(k);
      localStorage.setItem(LS_EXPANDED, JSON.stringify(arr));
    } catch(e) {}
  }
  function applyExpandedState() {
    var set = getExpandedSet();
    var dirs = document.querySelectorAll('.tree .dir');
    for (var i=0; i<dirs.length; i++) {
      var p = dirs[i].getAttribute('data-dirpath');
      var expanded = Boolean(p && set[p]);
      dirs[i].classList.toggle('collapsed', !expanded);
      var button = dirs[i].querySelector(':scope > .name');
      if (button) button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  }

  function revealParents(fileEl) {
    try {
      var li = fileEl.parentElement;
      while (li) {
        if (li.classList && li.classList.contains('dir')) {
          li.classList.remove('collapsed');
          var button = li.querySelector(':scope > .name');
          if (button) button.setAttribute('aria-expanded', 'true');
        }
        li = li.parentElement;
      }
      var set = getExpandedSet();
      var dirs = document.querySelectorAll('.tree .dir');
      for (var i=0;i<dirs.length;i++){
        var key = dirs[i].getAttribute('data-dirpath');
        if (!key) continue;
        set[key] = !dirs[i].classList.contains('collapsed');
      }
      saveExpandedSet(set);
    } catch(e) {}
  }

  function loadFile(meta) {
    ensureEditor();
    editor.setValue('// Загрузка...', -1);
    setButtons(false);
    setToolbar(meta, null);
    setMode(meta.ext);

    var url = '$loadUrl';
    if (url === '#') {
      editor.setValue('// Шаблон не выбран', -1);
      toastError('Шаблон не выбран');
      return;
    }

    var full;
    try {
      full = new URL(url, window.location.origin);
      full.searchParams.set('root', meta.root);
      full.searchParams.set('path', meta.path);
    } catch(e) {
      editor.setValue('// Ошибка формирования URL: ' + e.message, -1);
      toastError('Ошибка формирования URL: ' + e.message);
      return;
    }

    fetch(full.toString(), { credentials:'same-origin' })
      .then(parseJsonSafe)
      .then(function(json){
        if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Не удалось загрузить файл');

        editor.setValue(json.content ? json.content : '', -1);
        setMode(meta.ext);
        setToolbar(meta, json.from);
        setButtons(true);

        clearAllDots();
        markDotForPath(meta.path, json.from === 'db');

        current = meta;
        saveOpenFile(meta);
      })
      .catch(function(e){
        ensureEditor();
        editor.setValue('// Ошибка: ' + e.message, -1);
        setButtons(false);
        toastError(e.message);
      });
  }

  function initFoldersToggle() {
    var set = getExpandedSet();
    var dirNames = document.querySelectorAll('.tree .dir > .name');
    for (var i=0; i<dirNames.length; i++) {
      dirNames[i].addEventListener('click', function(){
        var li = this.parentElement;
        var key = li.getAttribute('data-dirpath');
        if (!key) return;
        if (li.classList.contains('collapsed')) {
          li.classList.remove('collapsed'); set[key] = true;
        } else {
          li.classList.add('collapsed'); set[key] = false;
        }
        this.setAttribute('aria-expanded', li.classList.contains('collapsed') ? 'false' : 'true');
        saveExpandedSet(set);
      });
    }
  }

  function initFilesClick() {
    var fileEls = document.querySelectorAll('.tree .file');
    for (var i=0; i<fileEls.length; i++) {
      fileEls[i].addEventListener('click', function(){
        var metaStr = this.getAttribute('data-meta');
        try {
          var meta = JSON.parse(metaStr);
          revealParents(this);
          loadFile(meta);
        } catch(e) {
          ensureEditor();
          editor.setValue('// Ошибка чтения meta: ' + e.message, -1);
          toastError('Ошибка чтения meta: ' + e.message);
        }
      });
    }
  }

  function restoreTreeScroll() {
    try {
      var v = localStorage.getItem(LS_TREE_SCROLL);
      var top = v ? parseInt(v, 10) : 0;
      if (!isNaN(top)) {
        var tree = document.getElementById('tree');
        if (tree) tree.scrollTop = top;
      }
    } catch(e) {}
  }
  function initTreeScrollSaver() {
    var tree = document.getElementById('tree');
    if (!tree) return;
    tree.addEventListener('scroll', function(){
      try { localStorage.setItem(LS_TREE_SCROLL, String(tree.scrollTop)); } catch(e) {}
    });
  }

  function autoOpenRememberedOrFirst() {
    var remembered = getSavedOpenFile();
    if (remembered && remembered.root && remembered.path) {
      var fileEls = document.querySelectorAll('.tree .file');
      for (var i=0;i<fileEls.length;i++) {
        try {
          var meta = JSON.parse(fileEls[i].getAttribute('data-meta'));
          if (meta && meta.root === remembered.root && meta.path === remembered.path) {
            revealParents(fileEls[i]);
            loadFile(remembered);
            return;
          }
        } catch(e) {}
      }
    }
    var firstFile = document.querySelector('.tree .file');
    if (firstFile) {
      try {
        var meta = JSON.parse(firstFile.getAttribute('data-meta'));
        loadFile(meta);
      } catch(e) {}
    }
  }

  // init
  applyExpandedState();
  initFoldersToggle();
  initFilesClick();
  restoreTreeScroll();
  initTreeScrollSaver();
  autoOpenRememberedOrFirst();

  // SAVE
  document.getElementById('saveBtn').addEventListener('click', function(){
    if (!current.path) return;

    var url = '$saveUrl';
    if (url === '#') { toastError('Шаблон не выбран'); return; }

    var fd = new FormData();
    fd.set('root', current.root);
    fd.set('path', current.path);
    fd.set('content', editor.getValue());
    if (window.__csrfParam && window.__csrfToken) {
      fd.set(window.__csrfParam, window.__csrfToken);
    }

    fetch(url, {
      method:'POST',
      body: fd,
      credentials:'same-origin',
      headers: { 'X-CSRF-Token': window.__csrfToken || '' }
    })
      .then(parseJsonSafe)
      .then(function(json){
        if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Не удалось сохранить файл');
        setToolbar(current, 'db');
        markDotForPath(current.path, true);
        toastSuccess(json.message || 'Сохранено в БД');

        // Если бэкенд компилировал SCSS — покажем уведомление
        if (json.compile) {
          if (json.compile.success) {
            toastSuccess(json.compile.message || 'SCSS успешно собран');
          } else {
            toastError(json.compile.message || 'Не удалось собрать SCSS');
          }
        }
      })
      .catch(function(e){
        toastError(e.message);
      });
  });

  // REVERT
  document.getElementById('revertBtn').addEventListener('click', function(){
    if (!current.path) return;
    if (!confirm('Удалить оверрайд в БД и вернуть версию с диска?')) return;

    var url = '$revertUrl';
    if (url === '#') { toastError('Шаблон не выбран'); return; }

    var fd = new FormData();
    fd.set('root', current.root);
    fd.set('path', current.path);
    if (window.__csrfParam && window.__csrfToken) {
      fd.set(window.__csrfParam, window.__csrfToken);
    }

    fetch(url, {
      method:'POST',
      body: fd,
      credentials:'same-origin',
      headers: { 'X-CSRF-Token': window.__csrfToken || '' }
    })
      .then(parseJsonSafe)
      .then(function(json){
        if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Не удалось удалить переопределение');
        loadFile(current);
        markDotForPath(current.path, false);
        toastSuccess(json.message || 'Откат выполнен');
      })
      .catch(function(e){
        toastError(e.message);
      });
  });

})();
JS;
$this->registerJs($js);
