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

$css = <<<CSS
.editor-wrap { display:flex; gap:16px; }
.left { width:380px; }
.tree { max-height: 70vh; overflow:auto; border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#0f1216; color:#e5e7eb; }
.tree h3 { margin: 8px; font-weight:700; text-transform:uppercase; font-size:12px; opacity:.85; }
.tree ul { list-style:none; padding-left:16px; margin:0; }
.tree li { margin:2px 0; }
.tree .dir > .name { font-weight:700; margin:6px 0; cursor:pointer; display:flex; align-items:center; gap:6px; user-select:none; }
.tree .dir > .name::before { content: '▾'; display:inline-block; width:1em; text-align:center; }
.tree .dir.collapsed > .name::before { content: '▸'; }
.tree .dir.collapsed > ul { display:none; }
.tree .file { cursor:pointer; padding:2px 0; display:flex; align-items:center; gap:6px; }
.tree .file .badge { font-size:11px; opacity:.7; margin-left:auto; }
.tree .file .dot { width:8px; height:8px; border-radius:50%; background:#22c55e; display:none; }
.pane { flex:1; display:flex; flex-direction:column; min-width:0; }
#editor { height: 65vh; width: 100%; border:1px solid #e5e7eb; border-radius:8px; }
.toolbar { display:flex; gap:8px; align-items:center; margin: 8px 0; }
.status { margin-left:auto; opacity:.8; font-size:12px; }
.debug { margin:10px 0; padding:10px; border:1px dashed #888; font-size:12px; background:#111; color:#ddd; }
.actions { display:flex; gap:8px; margin-top:8px; }
.toast-message_text { display:inline-block; margin-left:8px; }
CSS;
$this->registerCss($css);

/** Рендер дерева (все папки по умолчанию свернуты) */
function renderTree(array $nodes, $rootKey = '', $prefix = '') {
    if (empty($nodes)) return;
    echo '<ul>';
    foreach ($nodes as $n) {
        if (isset($n['type']) && $n['type'] === 'dir') {
            $dirPath = $prefix . $n['name'] . '/';
            $dataPath = Html::encode($rootKey . '://' . $dirPath);
            echo '<li class="dir collapsed" data-dirpath="'.$dataPath.'"><div class="name">📁 ' . Html::encode($n['name']) . '</div>';
            renderTree($n['children'], $rootKey, $dirPath);
            echo '</li>';
        } elseif (isset($n['type']) && $n['type'] === 'file') {
            $meta = Html::encode(json_encode([
                                                 'root' => $n['root'],
                                                 'path' => $n['path'],
                                                 'ext'  => $n['ext'],
                                             ]));
            echo '<li class="file" data-meta="'.$meta.'">'
                . '📄 ' . Html::encode($n['name'])
                . '<span class="dot" title="Есть оверрайд в БД"></span>'
                . '<span class="badge">'.strtoupper($n['ext']).'</span>'
                . '</li>';
        }
    }
    echo '</ul>';
}
?>

    <h1><?= Html::encode($this->title) ?></h1>

<?php if (!empty($debug)): ?>
    <div class="debug">
        <strong>Debug paths</strong>
        <ul style="margin:6px 0;">
            <?php foreach ($debug as $d): ?>
                <li>
                    <code><?= Html::encode($d['alias']) ?></code> →
                    <code><?= Html::encode($d['path']) ?></code>,
                    exists: <strong><?= Html::encode($d['exists']) ?></strong>,
                    top-level nodes: <?= (int)$d['count'] ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

    <div class="d-flex align-items-center" style="gap:12px; margin-bottom:12px;">
        <div><strong>Текущий Template:</strong></div>
        <form method="get" action="<?= Url::to(['template/index']) ?>">
            <select name="templateId" onchange="this.form.submit()">
                <?php foreach ($templates as $t): ?>
                    <option value="<?= (int)$t->id ?>" <?= ($selectedTemplate && $t->id === $selectedTemplate->id) ? 'selected' : '' ?>>
                        #<?= (int)$t->id ?> — <?= Html::encode($t->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="ms-auto">
            <a class="btn btn-secondary btn-sm" href="<?= $selectedId ? Url::to(['template/settings', 'id' => $selectedId]) : '#' ?>">Настройки Template</a>
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
                    <div id="filePath" style="opacity:.7;"></div>
                </div>
                <div class="status">Источник: <span id="sourceBadge">—</span></div>
            </div>
            <div id="editor">// Выберите файл слева…</div>
            <div class="actions">
                <button id="saveBtn" class="btn btn-primary" disabled>Сохранить в БД</button>
                <button id="revertBtn" class="btn btn-outline-danger" disabled>Откатить (удалить оверрайд)</button>
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
    try { toastr.success('<i class=\\'fas fa-check-circle\\'></i><div class=\\'toast-message_text\\'>' + (msg || 'OK') + '</div>', '', {progressBar:true, positionClass:'toast-top-right', escapeHtml:false}); } catch(e) {}
  }
  function toastError(msg){
    try { toastr.error('<i class=\\'fas fa-exclamation-circle\\'></i><div class=\\'toast-message_text\\'>' + (msg || 'Ошибка') + '</div>', '', {progressBar:true, positionClass:'toast-top-right', escapeHtml:false}); } catch(e) {}
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
    badge.textContent     = from ? ((from === 'db') ? 'DB override' : 'Filesystem') : '—';
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
      throw new Error(t ? t.substring(0, 200) : 'Non-JSON response');
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
      if (p && set[p]) dirs[i].classList.remove('collapsed'); else dirs[i].classList.add('collapsed');
    }
  }

  function revealParents(fileEl) {
    try {
      var li = fileEl.parentElement;
      while (li) {
        if (li.classList && li.classList.contains('dir')) li.classList.remove('collapsed');
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
      editor.setValue('// Template не определён', -1);
      toastError('Template не определён');
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
        if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Load failed');

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
    if (url === '#') { toastError('Template не определён'); return; }

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
        if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Save failed');
        setToolbar(current, 'db');
        markDotForPath(current.path, true);
        toastSuccess(json.message || 'Сохранено в БД');

        // Если бэкенд компилировал SCSS — покажем уведомление
        if (json.compile) {
          if (json.compile.success) {
            toastSuccess(json.compile.message || 'SCSS compiled');
          } else {
            toastError(json.compile.message || 'SCSS compile failed');
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
    if (url === '#') { toastError('Template не определён'); return; }

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
        if (!json || !json.success) throw new Error((json && json.message) ? json.message : 'Revert failed');
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
