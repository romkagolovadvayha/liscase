<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers[] $serversWithFtp */

$this->title = 'FTP: все сервера';
$this->params['breadcrumbs'][] = ['label' => 'FTP менеджер', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$listUrl = Url::to(['list']);
$downloadUrl = Url::to(['download']);
$uploadAllUrl = Url::to(['upload-all']);
$csrf = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
?>

<div class="ftp-manager-page w-full flex flex-col min-h-0 flex-1">
    <div class="flex items-center justify-between gap-4 flex-wrap p-4 border-b border-[hsl(0_0%_15.3%_/_1)]">
        <div class="flex items-center gap-3 flex-wrap">
            <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0">FTP: одна папка на всех серверах</h2>
            <?= Html::a('<i class="fas fa-arrow-left mr-1"></i> Обычный FTP менеджер', ['index'], ['class' => 'text-xs text-blue-400 hover:text-blue-300']) ?>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <label for="ftp-broadcast-path" class="text-xs text-gray-400 whitespace-nowrap">Путь на FTP:</label>
            <input type="text" id="ftp-broadcast-path" class="ds-input text-sm min-w-[220px] max-w-[420px]" value="/" placeholder="/oxide/plugins" autocomplete="off">
            <button type="button" id="ftp-broadcast-refresh-all" class="ds-btn ds-btn--primary text-sm py-1.5 px-3">
                <i class="fas fa-sync-alt mr-1"></i> Обновить все
            </button>
            <button type="button" id="ftp-broadcast-upload-all-btn" class="ds-btn ds-btn--secondary text-sm py-1.5 px-3">
                <i class="fas fa-cloud-upload-alt mr-1"></i> Загрузить на все
            </button>
        </div>
    </div>

    <?php if (empty($serversWithFtp)): ?>
        <div class="p-8 text-center text-gray-500 text-sm">Нет серверов с заполненными FTP-данными.</div>
    <?php else: ?>
        <div class="ftp-tabs-wrap border-b border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_18%_/_1)] overflow-x-auto">
            <div class="ftp-tabs flex items-center gap-0 min-h-0 p-0 m-0" id="ftp-broadcast-tabs" role="tablist" aria-label="FTP-серверы">
                <?php foreach ($serversWithFtp as $idx => $s): ?>
                    <button type="button" id="ftp-broadcast-tab-<?= (int)$s->id ?>" class="ftp-tab-item ftp-broadcast-tab flex items-center border-b-2 border-transparent -mb-px cursor-pointer px-4 py-3 text-sm text-gray-400 hover:text-white hover:bg-[hsl(0_0%_22%_/_1)] <?= $idx === 0 ? 'active' : '' ?>"
                        data-server-id="<?= (int)$s->id ?>" role="tab" aria-controls="ftp-broadcast-panel-<?= (int)$s->id ?>" aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>" tabindex="<?= $idx === 0 ? '0' : '-1' ?>">
                        <i class="fas fa-server mr-2 text-gray-500" aria-hidden="true"></i>
                        <span><?= Html::encode($s->name) ?></span>
                        <span class="text-gray-600 ml-1">(<?= Html::encode($s->tag) ?>)</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ftp-content flex-1 flex flex-col min-h-0 overflow-hidden" id="ftp-broadcast-panels-root">
            <?php foreach ($serversWithFtp as $idx => $s): ?>
                <div class="ftp-panel ftp-broadcast-panel <?= $idx === 0 ? '' : 'hidden' ?> flex flex-col min-h-0 h-full flex-1"
                     id="ftp-broadcast-panel-<?= (int)$s->id ?>" data-server-id="<?= (int)$s->id ?>" role="tabpanel" aria-labelledby="ftp-broadcast-tab-<?= (int)$s->id ?>">
                    <div class="ftp-toolbar flex items-center gap-3 flex-wrap p-3 border-b border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_20.4%_/_1)]">
                        <span class="text-xs text-gray-500 ftp-broadcast-panel-path"></span>
                        <div class="ml-auto flex items-center gap-2 flex-shrink-0">
                            <button type="button" class="ftp-broadcast-one-refresh ds-btn ds-btn--secondary text-sm py-1.5 px-2.5">
                                <i class="fas fa-sync-alt mr-1"></i> Этот сервер
                            </button>
                        </div>
                    </div>
                    <div class="ftp-list-wrap flex-1 overflow-auto p-3">
                        <div class="ftp-loading text-gray-500 text-sm py-4 text-center">Укажите путь и нажмите «Обновить все».</div>
                        <table class="ftp-table w-full text-sm text-left hidden" aria-label="Файлы FTP-сервера <?= Html::encode($s->name) ?>">
                            <thead>
                                <tr class="text-gray-400 border-b border-[hsl(0_0%_15.3%_/_1)]">
                                    <th class="py-2 pr-2">Имя</th>
                                    <th class="py-2 pr-2 w-24">Размер</th>
                                    <th class="py-2 w-28">Действия</th>
                                </tr>
                            </thead>
                            <tbody class="ftp-tbody"></tbody>
                        </table>
                        <div class="ftp-error hidden text-red-400 text-sm py-2"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="ftp-modal-overlay fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4" id="ftp-broadcast-upload-modal" role="dialog" aria-modal="true" aria-labelledby="ftp-broadcast-upload-title" aria-hidden="true">
    <div class="ftp-modal bg-[hsl(0_0%_20.4%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between p-3 border-b border-[hsl(0_0%_15.3%_/_1)]">
            <h2 class="text-white font-medium m-0" id="ftp-broadcast-upload-title">Загрузить на все серверы</h2>
            <button type="button" class="ftp-broadcast-upload-close text-gray-400 hover:text-white text-2xl leading-none" aria-label="Закрыть загрузку файла">&times;</button>
        </div>
        <form id="ftp-broadcast-upload-form" class="p-4">
            <input type="hidden" name="<?= $csrf ?>" value="<?= Html::encode($csrfToken) ?>">
            <input type="hidden" name="path" id="ftp-broadcast-upload-path">
            <div class="mb-3">
                <label for="ftp-broadcast-upload-file" class="block text-gray-400 text-xs mb-1">Файл (одинаковое имя на каждом FTP)</label>
                <input type="file" name="file" id="ftp-broadcast-upload-file" class="ds-input w-full text-sm" required>
            </div>
            <p class="text-xs text-gray-500 mb-3">Файл будет записан в текущую папку (поле «Путь на FTP») на каждом сервере с настроенным FTP.</p>
            <div id="ftp-broadcast-upload-progress" class="hidden text-sm text-gray-400 mb-2">Отправка…</div>
            <div id="ftp-broadcast-upload-result" class="hidden text-sm mb-3 max-h-40 overflow-y-auto"></div>
            <div class="flex justify-end gap-2">
                <button type="button" class="ds-btn ds-btn--secondary ftp-broadcast-upload-close">Закрыть</button>
                <button type="submit" class="ds-btn ds-btn--primary" id="ftp-broadcast-upload-submit">Загрузить</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($serversWithFtp)): ?>
<script>
(function() {
    var listUrl = <?= json_encode($listUrl) ?>;
    var downloadUrl = <?= json_encode($downloadUrl) ?>;
    var uploadAllUrl = <?= json_encode($uploadAllUrl) ?>;
    var csrfParam = <?= json_encode($csrf) ?>;
    var csrfToken = <?= json_encode($csrfToken) ?>;

    function getPathInput() {
        return document.getElementById('ftp-broadcast-path');
    }
    function currentPath() {
        var p = (getPathInput().value || '/').trim();
        if (!p) p = '/';
        p = p.replace(/\\/g, '/');
        if (p[0] !== '/') p = '/' + p;
        return p;
    }

    function getPanel(serverId) {
        return document.querySelector('.ftp-broadcast-panel[data-server-id="' + serverId + '"]');
    }
    function getTab(serverId) {
        return document.querySelector('.ftp-broadcast-tab[data-server-id="' + serverId + '"]');
    }
    function setActiveTab(serverId) {
        serverId = String(serverId);
        document.querySelectorAll('.ftp-broadcast-tab').forEach(function(t) {
            var selected = t.getAttribute('data-server-id') === serverId;
            t.classList.toggle('active', selected);
            t.setAttribute('aria-selected', selected ? 'true' : 'false');
            t.tabIndex = selected ? 0 : -1;
        });
        document.querySelectorAll('.ftp-broadcast-panel').forEach(function(p) {
            p.classList.toggle('hidden', p.getAttribute('data-server-id') !== serverId);
        });
    }

    document.querySelectorAll('.ftp-broadcast-tab').forEach(function(tab) {
        tab.addEventListener('click', function() { setActiveTab(tab.getAttribute('data-server-id')); });
        tab.addEventListener('keydown', function(event) {
            var tabs = Array.prototype.slice.call(document.querySelectorAll('.ftp-broadcast-tab'));
            var index = tabs.indexOf(tab);
            var target = null;
            if (event.key === 'ArrowRight') target = tabs[(index + 1) % tabs.length];
            if (event.key === 'ArrowLeft') target = tabs[(index - 1 + tabs.length) % tabs.length];
            if (event.key === 'Home') target = tabs[0];
            if (event.key === 'End') target = tabs[tabs.length - 1];
            if (!target) return;
            event.preventDefault();
            setActiveTab(target.getAttribute('data-server-id'));
            target.focus();
        });
    });

    function formatSize(bytes) {
        if (bytes === 0) return '—';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function loadListForServer(serverId, path) {
        var panel = getPanel(serverId);
        if (!panel) return;
        panel.querySelector('.ftp-broadcast-panel-path').textContent = path;
        panel.querySelector('.ftp-loading').classList.remove('hidden');
        panel.querySelector('.ftp-loading').textContent = 'Загрузка...';
        panel.querySelector('.ftp-table').classList.add('hidden');
        panel.querySelector('.ftp-error').classList.add('hidden');

        fetch(listUrl + '?server_id=' + encodeURIComponent(serverId) + '&path=' + encodeURIComponent(path), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                panel.querySelector('.ftp-loading').classList.add('hidden');
                if (data.success) {
                    renderTable(panel, serverId, path, data.items);
                    panel.querySelector('.ftp-table').classList.remove('hidden');
                } else {
                    panel.querySelector('.ftp-error').textContent = data.error || 'Ошибка';
                    panel.querySelector('.ftp-error').classList.remove('hidden');
                }
            })
            .catch(function() {
                panel.querySelector('.ftp-loading').classList.add('hidden');
                panel.querySelector('.ftp-error').textContent = 'Ошибка сети';
                panel.querySelector('.ftp-error').classList.remove('hidden');
            });
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
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    getPathInput().value = item.path;
                    refreshAll();
                });
                tdName.appendChild(a);
                tdSize.textContent = '—';
            } else {
                tdName.innerHTML = '<i class="fas fa-file text-gray-500 mr-2"></i>' + escapeHtml(item.name);
                tdSize.textContent = formatSize(item.size);
                var downBtn = document.createElement('button');
                downBtn.type = 'button';
                downBtn.className = 'ds-btn ds-btn--secondary text-xs py-0.5 px-1.5';
                downBtn.innerHTML = '<i class="fas fa-download"></i>';
                downBtn.title = 'Скачать';
                downBtn.addEventListener('click', function() {
                    window.location.href = downloadUrl + '?server_id=' + serverId + '&path=' + encodeURIComponent(item.path);
                });
                tdActions.appendChild(downBtn);
            }
            tr.appendChild(tdName);
            tr.appendChild(tdSize);
            tr.appendChild(tdActions);
            tbody.appendChild(tr);
        });
    }

    function refreshAll() {
        var path = currentPath();
        document.querySelectorAll('.ftp-broadcast-panel').forEach(function(panel) {
            var sid = panel.getAttribute('data-server-id');
            loadListForServer(sid, path);
        });
    }

    document.getElementById('ftp-broadcast-refresh-all').addEventListener('click', refreshAll);

    document.querySelectorAll('.ftp-broadcast-one-refresh').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var panel = btn.closest('.ftp-broadcast-panel');
            if (!panel) return;
            loadListForServer(panel.getAttribute('data-server-id'), currentPath());
        });
    });

    var uploadModal = document.getElementById('ftp-broadcast-upload-modal');
    var lastUploadDialogTrigger = null;
    function openUploadModal() {
        lastUploadDialogTrigger = document.activeElement;
        document.getElementById('ftp-broadcast-upload-path').value = currentPath();
        document.getElementById('ftp-broadcast-upload-file').value = '';
        document.getElementById('ftp-broadcast-upload-result').classList.add('hidden');
        document.getElementById('ftp-broadcast-upload-result').innerHTML = '';
        document.getElementById('ftp-broadcast-upload-progress').classList.add('hidden');
        document.getElementById('ftp-broadcast-upload-submit').disabled = false;
        uploadModal.classList.remove('hidden');
        uploadModal.classList.add('flex');
        uploadModal.setAttribute('aria-hidden', 'false');
        document.getElementById('ftp-broadcast-upload-file').focus();
    }
    function closeUploadModal() {
        uploadModal.classList.add('hidden');
        uploadModal.classList.remove('flex');
        uploadModal.setAttribute('aria-hidden', 'true');
        if (lastUploadDialogTrigger && document.contains(lastUploadDialogTrigger)) lastUploadDialogTrigger.focus();
    }
    document.getElementById('ftp-broadcast-upload-all-btn').addEventListener('click', openUploadModal);
    uploadModal.querySelectorAll('.ftp-broadcast-upload-close').forEach(function(b) { b.addEventListener('click', closeUploadModal); });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !uploadModal.classList.contains('hidden')) closeUploadModal();
    });

    document.getElementById('ftp-broadcast-upload-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        var progress = document.getElementById('ftp-broadcast-upload-progress');
        var resultEl = document.getElementById('ftp-broadcast-upload-result');
        var submitBtn = document.getElementById('ftp-broadcast-upload-submit');
        progress.classList.remove('hidden');
        resultEl.classList.add('hidden');
        submitBtn.disabled = true;
        fetch(uploadAllUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                progress.classList.add('hidden');
                submitBtn.disabled = false;
                resultEl.classList.remove('hidden');
                var html = '';
                if (data.summary) {
                    html += '<div class="mb-2 text-gray-300">Готово: ' + data.summary.ok + ' из ' + data.summary.total + '</div>';
                }
                if (data.results) {
                    data.results.forEach(function(row) {
                        var ok = row.success;
                        html += '<div class="' + (ok ? 'text-green-400' : 'text-red-400') + '">' + escapeHtml(row.name) + (ok ? ' — ок' : ' — ' + escapeHtml(row.error || 'ошибка')) + '</div>';
                    });
                }
                if (!data.success && !data.results) {
                    html = '<div class="text-red-400">' + escapeHtml(data.error || 'Ошибка') + '</div>';
                }
                resultEl.innerHTML = html;
                if (data.results && data.summary && data.summary.ok > 0) {
                    refreshAll();
                }
            })
            .catch(function() {
                progress.classList.add('hidden');
                submitBtn.disabled = false;
                resultEl.classList.remove('hidden');
                resultEl.innerHTML = '<div class="text-red-400">Ошибка сети</div>';
            });
    });
})();
</script>
<?php endif; ?>
