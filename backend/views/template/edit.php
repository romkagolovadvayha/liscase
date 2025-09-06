<?php
/**
 * @var \common\models\template\Template $template
 * @var array $trees ['frontend'=>treeArray, 'common'=>treeArray]
 */
use yii\helpers\Url;
use yii\helpers\Html;

$this->title = 'Edit Template: ' . Html::encode($template->name);
$loadUrl = Url::to(['template/load-file', 'id' => $template->id]);
$saveUrl = Url::to(['template/save-file', 'id' => $template->id]);
$revertUrl = Url::to(['template/revert-file', 'id' => $template->id]);

$css = <<<CSS
.editor-wrap { display:flex; gap:16px; }
.tree { width: 360px; max-height: 75vh; overflow:auto; border:1px solid var(--border-color, #e5e7eb); border-radius:8px; padding:8px; background:var(--background-teritiary, #0f1216); }
.tree h3 { margin: 8px 8px 12px; font-weight:700; text-transform:uppercase; font-size:12px; opacity:.85; }
.tree ul { list-style:none; padding-left:14px; }
.tree .dir > .name { font-weight:700; margin:6px 0; cursor:default; }
.tree .file { cursor:pointer; padding:2px 0; }
.tree .file .badge { font-size:11px; opacity:.7; margin-left:6px; }
.pane { flex:1; display:flex; flex-direction:column; min-width:0; }
#editor { height: 65vh; width: 100%; border:1px solid var(--border-color, #e5e7eb); border-radius:8px; }
.toolbar { display:flex; gap:8px; align-items:center; margin: 0 0 8px; }
.status { margin-left:auto; opacity:.8; font-size:12px; }
.empty-hint { opacity:.7; font-size:14px; padding:16px; }
CSS;
$this->registerCss($css);

// Ace Editor (CDN)
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js', ['defer' => true]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ext-language_tools.min.js', ['defer' => true]);

function renderTree(array $nodes)
{
    if (empty($nodes)) return '';
    echo '<ul>';
    foreach ($nodes as $n) {
        if ($n['type'] === 'dir') {
            echo '<li class="dir"><div class="name">📁 ' . Html::encode($n['name']) . '</div>';
            renderTree($n['children']);
            echo '</li>';
        } else {
            $label = '📄 ' . Html::encode($n['name']);
            $meta = Html::tag('span', strtoupper($n['ext']), ['class' => 'badge']);
            $data = [
                'root' => $n['root'],
                'path' => $n['path'],
                'ext'  => $n['ext'],
            ];
            echo '<li class="file" data-meta=\''.Html::encode(json_encode($data)).'\'>' . $label . ' ' . $meta . '</li>';
        }
    }
    echo '</ul>';
}
?>
    <div class="editor-wrap">
        <div class="tree">
            <h3>@frontend/views</h3>
            <?php renderTree($trees['frontend'] ?? []); ?>
            <h3 style="margin-top:16px;">@common/views</h3>
            <?php renderTree($trees['common'] ?? []); ?>
        </div>

        <div class="pane">
            <div class="toolbar">
                <div>
                    <strong id="fileLabel">Файл не выбран</strong>
                    <div class="p3" id="filePath" style="opacity:.7;"></div>
                </div>
                <div class="status">
                    Источник: <span id="sourceBadge">—</span>
                </div>
            </div>

            <div id="editor" class="empty-hint">Выберите файл слева…</div>

            <div style="margin-top:8px; display:flex; gap:8px;">
                <button id="saveBtn" class="btn btn-primary" disabled>Сохранить в БД</button>
                <button id="revertBtn" class="btn btn-outline-danger" disabled>Откатить (удалить оверрайд)</button>
            </div>
        </div>
    </div>

<?php
$js = <<<JS
let editor, current = {root:null, path:null, ext:null}, currentFrom = null;
const loadUrl = '$loadUrl';
const saveUrl = '$saveUrl';
const revertUrl = '$revertUrl';

function setModeByExt(ext) {
  const map = { php: 'ace/mode/php', twig: 'ace/mode/twig', scss: 'ace/mode/scss' };
  const mode = map[ext] || 'ace/mode/text';
  editor.session.setMode(mode);
}

function setToolbarLabels(meta, from) {
  document.getElementById('fileLabel').textContent = meta ? meta.path.split('/').pop() : 'Файл не выбран';
  document.getElementById('filePath').textContent = meta ? meta.root + '://' + meta.path : '';
  const badge = document.getElementById('sourceBadge');
  if (!from) { badge.textContent = '—'; return; }
  badge.textContent = (from === 'db') ? 'DB override' : 'Filesystem';
  badge.style.opacity = (from === 'db') ? '1' : '0.8';
}

function setButtonsEnabled(enabled) {
  document.getElementById('saveBtn').disabled = !enabled;
  document.getElementById('revertBtn').disabled = !enabled;
}

function enableEditor() {
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

document.querySelectorAll('.tree .file').forEach(el => {
  el.addEventListener('click', async () => {
    const meta = JSON.parse(el.dataset.meta);
    enableEditor();
    editor.setValue('// Загрузка...', -1);
    setModeByExt(meta.ext);
    setToolbarLabels(meta, null);
    setButtonsEnabled(false);
    try {
      const url = new URL(loadUrl, window.location.origin);
      url.searchParams.set('root', meta.root);
      url.searchParams.set('path', meta.path);
      const res = await fetch(url.toString(), { credentials:'same-origin' });
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Load failed');
      current = meta;
      currentFrom = json.from;
      setToolbarLabels(meta, json.from);
      editor.setValue(json.content ?? '', -1);
      setButtonsEnabled(true);
      setModeByExt(json.ext);
    } catch(e) {
      editor.setValue('// Ошибка загрузки: ' + e.message, -1);
      current = {root:null, path:null, ext:null};
      currentFrom = null;
      setButtonsEnabled(false);
    }
  });
});

document.getElementById('saveBtn').addEventListener('click', async () => {
  if (!current.path) return;
  const formData = new FormData();
  formData.set('root', current.root);
  formData.set('path', current.path);
  formData.set('content', editor.getValue());
  try {
    const res = await fetch(saveUrl, { method:'POST', body: formData, credentials:'same-origin' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Save failed');
    currentFrom = 'db';
    setToolbarLabels(current, currentFrom);
    alert('Сохранено');
  } catch(e) {
    alert('Ошибка: ' + e.message);
  }
});

document.getElementById('revertBtn').addEventListener('click', async () => {
  if (!current.path) return;
  if (!confirm('Удалить оверрайд и вернуть оригинал с диска?')) return;
  const formData = new FormData();
  formData.set('root', current.root);
  formData.set('path', current.path);
  try {
    const res = await fetch(revertUrl, { method:'POST', body: formData, credentials:'same-origin' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Revert failed');

    // Перечитать файл уже с диска
    const url = new URL(loadUrl, window.location.origin);
    url.searchParams.set('root', current.root);
    url.searchParams.set('path', current.path);
    const res2 = await fetch(url.toString(), { credentials:'same-origin' });
    const json2 = await res2.json();
    if (!json2.success) throw new Error(json2.message || 'Reload failed');

    editor.setValue(json2.content ?? '', -1);
    currentFrom = json2.from;
    setToolbarLabels(current, currentFrom);
    alert('Откат выполнен');
  } catch(e) {
    alert('Ошибка: ' + e.message);
  }
});
JS;
$this->registerJs($js);
