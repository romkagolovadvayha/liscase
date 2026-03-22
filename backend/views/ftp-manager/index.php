<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers[] $serversWithFtp */

$this->title = 'FTP менеджер';
$this->params['breadcrumbs'][] = $this->title;

$listUrl = Url::to(['list']);
$downloadUrl = Url::to(['download']);
$uploadUrl = Url::to(['upload']);
$deleteUrl = Url::to(['delete']);
$getContentUrl = Url::to(['get-content']);
$saveContentUrl = Url::to(['save-content']);
$createDirUrl = Url::to(['create-dir']);
$csrf = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;

$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerCssFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/meta.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/ini/ini.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/shell/shell.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
$this->registerJsFile('https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<div class="ftp-manager-page w-full flex flex-col min-h-0 flex-1">
    <div class="flex items-center justify-between gap-4 flex-wrap p-4 border-b border-[hsl(0_0%_15.3%_/_1)]">
        <div class="flex items-center gap-4 flex-wrap">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0">FTP менеджер</h2>
            <?= Html::a('<i class="fas fa-cloud-arrow-up mr-1"></i> Все сервера (одна папка)', ['broadcast'], ['class' => 'text-xs text-blue-400 hover:text-blue-300 whitespace-nowrap']) ?>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs text-gray-400">Открыть сервер:</label>
            <select id="ftp-server-select" class="ds-select bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] text-white rounded px-3 py-1.5 text-sm min-w-[200px]">
                <option value="">— Выберите сервер —</option>
                <?php foreach ($serversWithFtp as $s): ?>
                    <option value="<?= (int)$s->id ?>" data-name="<?= Html::encode($s->name) ?>"><?= Html::encode($s->name) ?> (<?= Html::encode($s->tag) ?>)</option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="ftp-open-server-btn" class="ds-btn ds-btn--primary text-sm py-1.5 px-3">
                <i class="fas fa-folder-open mr-1"></i> Открыть
            </button>
        </div>
    </div>

    <!-- Вкладки открытых серверов -->
    <div class="ftp-tabs-wrap border-b border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] overflow-x-auto">
        <ul class="ftp-tabs flex items-center gap-0 min-h-0 p-0 m-0 list-none" id="ftp-tabs-list"></ul>
    </div>

    <!-- Контент: файловый менеджер для активной вкладки -->
    <div class="ftp-content flex-1 flex flex-col min-h-0 overflow-hidden" id="ftp-content">
        <div class="ftp-empty flex-1 flex items-center justify-center text-gray-500 text-sm p-8" id="ftp-empty">
            Выберите сервер и нажмите «Открыть», чтобы управлять файлами.
        </div>
        <div class="ftp-panels hidden flex-1 flex flex-col min-h-0" id="ftp-panels"></div>
    </div>
</div>

<!-- Шаблон одной вкладки (сервер) -->
<template id="ftp-tab-tpl">
    <li class="ftp-tab-item flex items-center border-b-2 border-transparent -mb-px cursor-pointer px-4 py-3 text-sm text-gray-400 hover:text-white hover:bg-[hsl(0_0%_22%_/_1)]" data-server-id="">
        <i class="fas fa-server mr-2 text-gray-500"></i>
        <span class="ftp-tab-name"></span>
        <button type="button" class="ftp-tab-close ml-2 p-0.5 rounded hover:bg-red-600/30 hover:text-red-300 text-gray-500" title="Закрыть" aria-label="Закрыть">&times;</button>
    </li>
</template>

<!-- Шаблон панели файлов для одного сервера -->
<template id="ftp-panel-tpl">
    <div class="ftp-panel hidden flex flex-col min-h-0 h-full" data-server-id="">
        <div class="ftp-toolbar flex items-center gap-3 flex-wrap p-3 border-b border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)]">
            <nav class="ftp-breadcrumb flex items-center gap-0 text-sm flex-wrap min-w-0 rounded-md bg-[hsl(0_0%_15%_/_1)] px-2 py-1.5 border border-[hsl(0_0%_25%_/_1)]" aria-label="Путь"></nav>
            <div class="ml-auto flex items-center gap-2 flex-shrink-0">
                <button type="button" class="ftp-btn-refresh ds-btn ds-btn--secondary text-sm py-1.5 px-2.5"><i class="fas fa-sync-alt mr-1"></i> Обновить</button>
                <button type="button" class="ftp-btn-upload ds-btn ds-btn--secondary text-sm py-1.5 px-2.5"><i class="fas fa-upload mr-1"></i> Загрузить</button>
                <button type="button" class="ftp-btn-newdir ds-btn ds-btn--secondary text-sm py-1.5 px-2.5"><i class="fas fa-folder-plus mr-1"></i> Новая папка</button>
            </div>
        </div>
        <div class="ftp-list-wrap flex-1 overflow-auto p-3">
            <div class="ftp-loading text-gray-500 text-sm py-4 text-center">Загрузка...</div>
            <table class="ftp-table w-full text-sm text-left hidden">
                <thead>
                    <tr class="text-gray-400 border-b border-[hsl(0_0%_15.3%_/_1)]">
                        <th class="py-2 pr-2">Имя</th>
                        <th class="py-2 pr-2 w-24">Размер</th>
                        <th class="py-2 w-40">Действия</th>
                    </tr>
                </thead>
                <tbody class="ftp-tbody"></tbody>
            </table>
            <div class="ftp-error hidden text-red-400 text-sm py-2"></div>
        </div>
    </div>
</template>

<!-- Модальное окно: редактирование файла (большое, с подсветкой синтаксиса) -->
<div class="ftp-modal-overlay fixed inset-0 bg-black/70 z-[100] hidden items-center justify-center p-4" id="ftp-edit-modal">
    <div class="ftp-edit-modal-box bg-[hsl(0_0%_18%_/_1)] border border-[hsl(0_0%_25%_/_1)] rounded-xl shadow-2xl w-[92vw] max-w-[1600px] h-[88vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between flex-shrink-0 px-4 py-3 border-b border-[hsl(0_0%_25%_/_1)] bg-[hsl(0_0%_22%_/_1)]">
            <span class="text-white font-medium truncate flex items-center gap-2">
                <i class="fas fa-file-code text-blue-400 flex-shrink-0"></i>
                <span class="ftp-edit-filename truncate"></span>
            </span>
            <button type="button" class="ftp-modal-close text-gray-400 hover:text-white text-2xl leading-none p-1 rounded hover:bg-white/10 flex-shrink-0" aria-label="Закрыть">&times;</button>
        </div>
        <div class="flex-1 min-h-0 flex flex-col p-3 overflow-hidden">
            <div class="ftp-codemirror-wrap flex-1 min-h-0 rounded-lg overflow-hidden border border-[hsl(0_0%_25%_/_1)]">
                <textarea id="ftp-edit-content" class="w-full h-full font-mono text-sm" spellcheck="false" style="display:none;"></textarea>
            </div>
        </div>
        <div class="flex justify-end gap-2 flex-shrink-0 px-4 py-3 border-t border-[hsl(0_0%_25%_/_1)] bg-[hsl(0_0%_22%_/_1)]">
            <button type="button" class="ds-btn ds-btn--secondary ftp-modal-close">Отмена</button>
            <button type="button" class="ds-btn ds-btn--primary" id="ftp-save-content-btn"><i class="fas fa-save mr-1"></i> Сохранить</button>
        </div>
    </div>
</div>

<!-- Модальное окно: загрузка файла -->
<div class="ftp-modal-overlay fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" id="ftp-upload-modal">
    <div class="ftp-modal bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <span class="text-white font-medium">Загрузить файл</span>
            <button type="button" class="ftp-upload-modal-close text-gray-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <form id="ftp-upload-form" class="p-4">
            <input type="hidden" name="<?= $csrf ?>" value="<?= Html::encode($csrfToken) ?>">
            <input type="hidden" name="server_id" id="ftp-upload-server-id">
            <input type="hidden" name="path" id="ftp-upload-path">
            <div class="mb-3">
                <label class="block text-gray-400 text-xs mb-1">Файл</label>
                <input type="file" name="file" id="ftp-upload-file" class="ds-input w-full text-sm">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="ds-btn ds-btn--secondary ftp-upload-modal-close">Отмена</button>
                <button type="submit" class="ds-btn ds-btn--primary">Загрузить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: новая папка -->
<div class="ftp-modal-overlay fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" id="ftp-newdir-modal">
    <div class="ftp-modal bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <span class="text-white font-medium">Новая папка</span>
            <button type="button" class="ftp-newdir-modal-close text-gray-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <form id="ftp-newdir-form" class="p-4">
            <input type="hidden" name="<?= $csrf ?>" value="<?= Html::encode($csrfToken) ?>">
            <input type="hidden" name="server_id" id="ftp-newdir-server-id">
            <input type="hidden" name="path" id="ftp-newdir-path">
            <div class="mb-3">
                <label class="block text-gray-400 text-xs mb-1">Имя папки</label>
                <input type="text" name="name" id="ftp-newdir-name" class="ds-input w-full" placeholder="folder_name" required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="ds-btn ds-btn--secondary ftp-newdir-modal-close">Отмена</button>
                <button type="submit" class="ds-btn ds-btn--primary">Создать</button>
            </div>
        </form>
    </div>
</div>

<style>
.ftp-tab-item.active { color: #4a9eff; border-bottom-color: #4a9eff; background: hsl(0 0% 20.4% / 1); }
.ftp-modal-overlay.flex { display: flex !important; }
.ftp-table td { padding: 10px 12px 10px 0; border-bottom: 1px solid hsl(0 0% 15.3% / 1); color: hsl(0 0% 88%); vertical-align: middle; }
.ftp-table td a { color: #6bb3ff; text-decoration: none; }
.ftp-table td a:hover { text-decoration: underline; }
/* Хлебные крошки */
.ftp-breadcrumb { display: flex; align-items: center; flex-wrap: wrap; gap: 0; }
.ftp-breadcrumb .ftp-bc-item { display: inline-flex; align-items: center; gap: 0.25rem; }
.ftp-breadcrumb .ftp-bc-link { color: #6bb3ff; text-decoration: none; padding: 0.15rem 0.35rem; border-radius: 4px; white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.ftp-breadcrumb .ftp-bc-link:hover { background: hsl(0 0% 25% / 1); color: #8cc5ff; }
.ftp-breadcrumb .ftp-bc-sep { color: hsl(0 0% 45%); font-size: 0.75rem; padding: 0 0.2rem; user-select: none; }
.ftp-breadcrumb .ftp-bc-current { color: hsl(0 0% 85%); padding: 0.15rem 0.35rem; white-space: nowrap; max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
/* CodeMirror в модалке */
.ftp-codemirror-wrap .CodeMirror { height: 100%; min-height: 400px; font-size: 13px; }
.ftp-codemirror-wrap .CodeMirror-gutters { background: hsl(0 0% 14% / 1); border-right: 1px solid hsl(0 0% 22% / 1); }
</style>

<script>
(function() {
    var listUrl = <?= json_encode($listUrl) ?>;
    var downloadUrl = <?= json_encode($downloadUrl) ?>;
    var uploadUrl = <?= json_encode($uploadUrl) ?>;
    var deleteUrl = <?= json_encode($deleteUrl) ?>;
    var getContentUrl = <?= json_encode($getContentUrl) ?>;
    var saveContentUrl = <?= json_encode($saveContentUrl) ?>;
    var createDirUrl = <?= json_encode($createDirUrl) ?>;
    var csrfParam = <?= json_encode($csrf) ?>;
    var csrfToken = <?= json_encode($csrfToken) ?>;

    var openTabs = {};
    var activeServerId = null;

    function getPanel(serverId) {
        return document.querySelector('.ftp-panel[data-server-id="' + serverId + '"]');
    }
    function getTab(serverId) {
        return document.querySelector('.ftp-tab-item[data-server-id="' + serverId + '"]');
    }

    function openServer(serverId) {
        serverId = String(serverId);
        if (openTabs[serverId]) return;
        var opt = document.querySelector('#ftp-server-select option[value="' + serverId + '"]');
        var name = opt ? opt.getAttribute('data-name') || opt.textContent : 'Сервер ' + serverId;

        var tabTpl = document.getElementById('ftp-tab-tpl');
        var tab = tabTpl.content.cloneNode(true).querySelector('li');
        tab.setAttribute('data-server-id', serverId);
        tab.querySelector('.ftp-tab-name').textContent = name;
        tab.querySelector('.ftp-tab-close').addEventListener('click', function(e) { e.stopPropagation(); closeServer(serverId); });

        var panelTpl = document.getElementById('ftp-panel-tpl');
        var panel = panelTpl.content.cloneNode(true).querySelector('.ftp-panel');
        panel.setAttribute('data-server-id', serverId);

        document.getElementById('ftp-tabs-list').appendChild(tab);
        document.getElementById('ftp-panels').appendChild(panel);
        document.getElementById('ftp-empty').classList.add('hidden');
        document.getElementById('ftp-panels').classList.remove('hidden');

        openTabs[serverId] = { path: '/', name: name };
        panel.querySelector('.ftp-btn-refresh').addEventListener('click', function() { loadList(serverId); });
        panel.querySelector('.ftp-btn-upload').addEventListener('click', function() { openUploadModal(serverId); });
        panel.querySelector('.ftp-btn-newdir').addEventListener('click', function() { openNewDirModal(serverId); });

        tab.addEventListener('click', function() { setActiveTab(serverId); });

        setActiveTab(serverId);
        loadList(serverId);
    }

    function closeServer(serverId) {
        serverId = String(serverId);
        var tab = getTab(serverId);
        var panel = getPanel(serverId);
        if (tab) tab.remove();
        if (panel) panel.remove();
        delete openTabs[serverId];
        if (activeServerId === serverId) {
            var ids = Object.keys(openTabs);
            activeServerId = ids.length ? ids[0] : null;
            if (activeServerId) setActiveTab(activeServerId);
            else {
                document.getElementById('ftp-empty').classList.remove('hidden');
                document.getElementById('ftp-panels').classList.add('hidden');
            }
        }
    }

    function setActiveTab(serverId) {
        serverId = String(serverId);
        document.querySelectorAll('.ftp-tab-item').forEach(function(t) { t.classList.remove('active'); });
        document.querySelectorAll('.ftp-panel').forEach(function(p) { p.classList.add('hidden'); });
        var t = getTab(serverId);
        var p = getPanel(serverId);
        if (t) t.classList.add('active');
        if (p) p.classList.remove('hidden');
        activeServerId = serverId;
    }

    function renderBreadcrumb(serverId) {
        var info = openTabs[serverId];
        if (!info) return;
        var path = info.path || '/';
        var parts = path.split('/').filter(Boolean);
        var panel = getPanel(serverId);
        if (!panel) return;
        var nav = panel.querySelector('.ftp-breadcrumb');
        nav.innerHTML = '';
        var itemRoot = document.createElement('span');
        itemRoot.className = 'ftp-bc-item';
        var linkRoot = document.createElement('a');
        linkRoot.href = '#';
        linkRoot.className = 'ftp-bc-link';
        linkRoot.innerHTML = '<i class="fas fa-home"></i> Корень';
        linkRoot.addEventListener('click', function(e) { e.preventDefault(); openTabs[serverId].path = '/'; loadList(serverId); });
        itemRoot.appendChild(linkRoot);
        nav.appendChild(itemRoot);
        parts.forEach(function(p, i) {
            var sep = document.createElement('span');
            sep.className = 'ftp-bc-sep';
            sep.textContent = '/';
            nav.appendChild(sep);
            var pathSoFar = '/' + parts.slice(0, i + 1).join('/');
            var item = document.createElement('span');
            item.className = 'ftp-bc-item';
            if (i === parts.length - 1) {
                var current = document.createElement('span');
                current.className = 'ftp-bc-current';
                current.textContent = p;
                item.appendChild(current);
            } else {
                var link = document.createElement('a');
                link.href = '#';
                link.className = 'ftp-bc-link';
                link.textContent = p;
                link.addEventListener('click', function(e) { e.preventDefault(); openTabs[serverId].path = pathSoFar; loadList(serverId); });
                item.appendChild(link);
            }
            nav.appendChild(item);
        });
    }

    function loadList(serverId) {
        var info = openTabs[serverId];
        if (!info) return;
        var panel = getPanel(serverId);
        if (!panel) return;
        var path = info.path || '/';
        panel.querySelector('.ftp-loading').classList.remove('hidden');
        panel.querySelector('.ftp-table').classList.add('hidden');
        panel.querySelector('.ftp-error').classList.add('hidden');

        fetch(listUrl + '?server_id=' + encodeURIComponent(serverId) + '&path=' + encodeURIComponent(path), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                panel.querySelector('.ftp-loading').classList.add('hidden');
                if (data.success) {
                    renderTable(panel, serverId, path, data.items);
                    panel.querySelector('.ftp-table').classList.remove('hidden');
                    renderBreadcrumb(serverId);
                } else {
                    panel.querySelector('.ftp-error').textContent = data.error || 'Ошибка загрузки';
                    panel.querySelector('.ftp-error').classList.remove('hidden');
                }
            })
            .catch(function() {
                panel.querySelector('.ftp-loading').classList.add('hidden');
                panel.querySelector('.ftp-error').textContent = 'Ошибка сети';
                panel.querySelector('.ftp-error').classList.remove('hidden');
            });
    }

    function formatSize(bytes) {
        if (bytes === 0) return '—';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function renderTable(panel, serverId, currentPath, items) {
        var tbody = panel.querySelector('.ftp-tbody');
        tbody.innerHTML = '';
        items.forEach(function(item) {
            var tr = document.createElement('tr');
            var tdName = document.createElement('td');
            var tdSize = document.createElement('td');
            var tdActions = document.createElement('td');
            if (item.dir) {
                var a = document.createElement('a');
                a.href = '#';
                a.innerHTML = '<i class="fas fa-folder text-yellow-500 mr-2"></i>' + escapeHtml(item.name);
                a.addEventListener('click', function(e) { e.preventDefault(); openTabs[serverId].path = item.path; loadList(serverId); });
                tdName.appendChild(a);
                tdSize.textContent = '—';
            } else {
                tdName.innerHTML = '<i class="fas fa-file text-gray-500 mr-2"></i>' + escapeHtml(item.name);
                tdSize.textContent = formatSize(item.size);
                var downBtn = document.createElement('button');
                downBtn.type = 'button';
                downBtn.className = 'ds-btn ds-btn--secondary text-xs py-0.5 px-1.5 mr-1';
                downBtn.innerHTML = '<i class="fas fa-download"></i>';
                downBtn.title = 'Скачать';
                downBtn.addEventListener('click', function() { window.location.href = downloadUrl + '?server_id=' + serverId + '&path=' + encodeURIComponent(item.path); });
                tdActions.appendChild(downBtn);
                if (/\.(txt|json|xml|cfg|ini|log|yml|yaml|md|html|htm|css|js|php)$/i.test(item.name)) {
                    var editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.className = 'ds-btn ds-btn--secondary text-xs py-0.5 px-1.5 mr-1';
                    editBtn.innerHTML = '<i class="fas fa-edit"></i>';
                    editBtn.title = 'Редактировать';
                    editBtn.addEventListener('click', function() { openEditModal(serverId, item.path, item.name); });
                    tdActions.appendChild(editBtn);
                }
            }
            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'ds-btn ds-btn--secondary text-xs py-0.5 px-1.5 text-red-400 hover:text-red-300';
            delBtn.innerHTML = '<i class="fas fa-trash"></i>';
            delBtn.title = 'Удалить';
            delBtn.addEventListener('click', function() { if (confirm('Удалить «' + item.name + '»?')) deleteItem(serverId, item.path, function() { loadList(serverId); }); });
            tdActions.appendChild(delBtn);
            tr.appendChild(tdName);
            tr.appendChild(tdSize);
            tr.appendChild(tdActions);
            tbody.appendChild(tr);
        });
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function deleteItem(serverId, path, onSuccess) {
        var body = new FormData();
        body.append(csrfParam, csrfToken);
        body.append('server_id', serverId);
        body.append('path', path);
        fetch(deleteUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success && onSuccess) onSuccess(); else if (!data.success) alert(data.error || 'Ошибка'); });
    }

    var editModal = document.getElementById('ftp-edit-modal');
    var editTextarea = document.getElementById('ftp-edit-content');
    var editServerId = null, editPath = null;
    var ftpCodeMirrorEditor = null;
    var modeByExt = { json: 'application/json', js: 'javascript', jsx: 'javascript', ts: 'javascript', xml: 'xml', html: 'htmlmixed', htm: 'htmlmixed', css: 'css', php: 'application/x-httpd-php', ini: 'text/x-ini', cfg: 'text/x-ini', yml: 'yaml', yaml: 'yaml', md: 'markdown', sh: 'shell', bash: 'shell', txt: null, log: null };
    function getModeForFilename(filename) {
        var ext = (filename.split('.').pop() || '').toLowerCase();
        return modeByExt[ext] != null ? modeByExt[ext] : null;
    }
    function openEditModal(serverId, path, filename) {
        editServerId = serverId;
        editPath = path;
        editModal.querySelector('.ftp-edit-filename').textContent = filename;
        editTextarea.value = '';
        if (ftpCodeMirrorEditor) { ftpCodeMirrorEditor.toTextArea(); ftpCodeMirrorEditor = null; }
        editModal.classList.remove('hidden');
        editModal.classList.add('flex');
        fetch(getContentUrl + '?server_id=' + serverId + '&path=' + encodeURIComponent(path), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    editTextarea.value = data.content;
                    editTextarea.style.display = 'block';
                    var mode = getModeForFilename(filename);
                    ftpCodeMirrorEditor = window.CodeMirror.fromTextArea(editTextarea, {
                        mode: mode || 'text/plain',
                        theme: 'dracula',
                        lineNumbers: true,
                        lineWrapping: true,
                        indentUnit: 4,
                        indentWithTabs: false,
                        extraKeys: { 'Ctrl-S': function() { document.getElementById('ftp-save-content-btn').click(); } }
                    });
                    editTextarea.style.display = 'none';
                    setTimeout(function() { if (ftpCodeMirrorEditor) ftpCodeMirrorEditor.refresh(); }, 50);
                } else alert(data.error || 'Не удалось загрузить файл');
            });
    }
    function closeEditModal() {
        if (ftpCodeMirrorEditor) { ftpCodeMirrorEditor.toTextArea(); ftpCodeMirrorEditor = null; }
        editTextarea.style.display = 'none';
        editModal.classList.add('hidden');
        editModal.classList.remove('flex');
    }
    editModal.querySelectorAll('.ftp-modal-close').forEach(function(btn) { btn.addEventListener('click', closeEditModal); });
    editModal.addEventListener('click', function(e) { if (e.target === editModal) closeEditModal(); });
    document.getElementById('ftp-save-content-btn').addEventListener('click', function() {
        if (editServerId === null || editPath === null) return;
        var content = ftpCodeMirrorEditor ? ftpCodeMirrorEditor.getValue() : editTextarea.value;
        var body = new FormData();
        body.append(csrfParam, csrfToken);
        body.append('server_id', editServerId);
        body.append('path', editPath);
        body.append('content', content);
        fetch(saveContentUrl, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { closeEditModal(); loadList(editServerId); }
                else alert(data.error || 'Ошибка сохранения');
            });
    });

    var uploadModal = document.getElementById('ftp-upload-modal');
    function openUploadModal(serverId) {
        var info = openTabs[serverId];
        document.getElementById('ftp-upload-server-id').value = serverId;
        document.getElementById('ftp-upload-path').value = info ? info.path || '/' : '/';
        document.getElementById('ftp-upload-file').value = '';
        uploadModal.classList.remove('hidden');
        uploadModal.classList.add('flex');
    }
    uploadModal.querySelectorAll('.ftp-upload-modal-close').forEach(function(btn) { btn.addEventListener('click', function() { uploadModal.classList.add('hidden'); uploadModal.classList.remove('flex'); }); });
    document.getElementById('ftp-upload-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        fetch(uploadUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { uploadModal.classList.add('hidden'); uploadModal.classList.remove('flex'); loadList(activeServerId); }
                else alert(data.error || 'Ошибка загрузки');
            });
    });

    var newDirModal = document.getElementById('ftp-newdir-modal');
    function openNewDirModal(serverId) {
        var info = openTabs[serverId];
        document.getElementById('ftp-newdir-server-id').value = serverId;
        document.getElementById('ftp-newdir-path').value = info ? info.path || '/' : '/';
        document.getElementById('ftp-newdir-name').value = '';
        newDirModal.classList.remove('hidden');
        newDirModal.classList.add('flex');
    }
    newDirModal.querySelectorAll('.ftp-newdir-modal-close').forEach(function(btn) { btn.addEventListener('click', function() { newDirModal.classList.add('hidden'); newDirModal.classList.remove('flex'); }); });
    document.getElementById('ftp-newdir-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        fetch(createDirUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) { newDirModal.classList.add('hidden'); newDirModal.classList.remove('flex'); loadList(activeServerId); }
                else alert(data.error || 'Ошибка');
            });
    });

    document.getElementById('ftp-open-server-btn').addEventListener('click', function() {
        var sel = document.getElementById('ftp-server-select');
        var id = sel.value;
        if (id) openServer(id);
    });
})();
</script>
