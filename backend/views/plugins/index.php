<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var common\models\servers\Servers[] $servers */
/** @var array $pluginRows [ ['name' => ..., 'byServer' => [ tag => version ], 'projectVersion' => ... ], ... ] */
/** @var array $pluginRowsNotInstalled подмножество: плагины, не установленные хотя бы на одном сервере */
/** @var array $projectVersions */
/** @var int|null $cachedAt */

$this->title = 'Плагины';
$this->params['breadcrumbs'][] = $this->title;

$refreshUrl = Url::to(['refresh']);
$uploadPluginUrl = Url::to(['upload-plugin']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
?>

<div class="plugins-comparison-page w-full">
    <div class="flex items-center justify-between gap-4 flex-wrap p-4 border-b border-[hsl(0_0%_15.3%_/_1)]">
        <h2 class="text-sm font-semibold text-white uppercase tracking-wide m-0">Сравнение версий плагинов</h2>
        <div class="flex items-center gap-3">
            <?php if ($cachedAt): ?>
                <span class="text-gray-500 text-xs">Обновлено: <?= date('d.m.Y H:i:s', $cachedAt) ?></span>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-sync-alt mr-1"></i> Обновить данные', $refreshUrl, [
                'class' => 'ds-btn ds-btn--secondary text-sm',
                'title' => 'Сбросить кэш и загрузить список плагинов заново (o.plugins на всех серверах)',
            ]) ?>
        </div>
    </div>

    <?php if (!empty($servers) && !empty($pluginRows)): ?>
    <div class="plugins-tabs border-b border-[hsl(0_0%_15.3%_/_1)] px-4">
        <button type="button" class="plugins-tab-btn py-3 px-4 text-sm font-medium border-b-2 border-transparent text-gray-400 hover:text-white -mb-px" data-tab="all">Установленные (<?= count($pluginRows) ?>)</button>
        <button type="button" class="plugins-tab-btn py-3 px-4 text-sm font-medium border-b-2 border-transparent text-gray-400 hover:text-white -mb-px" data-tab="not-installed">Не установленные (<?= count($pluginRowsNotInstalled) ?>)</button>
    </div>
    <?php endif; ?>

    <div class="overflow-x-auto p-4">
        <?php if (empty($servers)): ?>
            <p class="text-gray-500">Нет активных серверов.</p>
        <?php elseif (empty($pluginRows)): ?>
            <p class="text-gray-500">Нет данных о плагинах (кэш пуст или RCON не вернул список). Нажмите «Обновить данные».</p>
        <?php else: ?>
            <?php
            $renderTable = function ($rows) use ($servers, $uploadPluginUrl, $csrfParam, $csrfToken) {
                if (empty($rows)) {
                    echo '<p class="text-gray-500">Нет записей.</p>';
                    return;
                }
                ?>
                <table class="plugins-table w-full text-sm border-collapse">
                    <thead>
                        <tr class="border-b border-[hsl(0_0%_15.3%_/_1)]">
                            <th class="text-left py-3 px-3 text-gray-400 font-semibold bg-[hsl(0_0%_18%_/_1)] sticky left-0 z-10 min-w-[200px]">Плагин</th>
                            <?php foreach ($servers as $s): ?>
                                <th class="text-left py-3 px-3 text-gray-400 font-semibold bg-[hsl(0_0%_18%_/_1)] whitespace-nowrap"><?= Html::encode($s->name) ?></th>
                            <?php endforeach; ?>
                            <th class="text-left py-3 px-3 text-green-400 font-semibold bg-[hsl(0_0%_18%_/_1)] whitespace-nowrap">Актуальная (проект)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $projectVer = $row['projectVersion'] ?? null;
                            $projVerNorm = $projectVer !== null ? trim((string)$projectVer) : '';
                            $rowHasOutdated = false;
                            foreach ($row['byServer'] as $v) {
                                $vn = $v !== null ? trim((string)$v) : '';
                                if ($projVerNorm !== '' && $vn !== '' && $vn !== $projVerNorm) {
                                    $rowHasOutdated = true;
                                    break;
                                }
                            }
                            $trClass = 'border-b border-[hsl(0_0%_15.3%_/_1)] hover:bg-[hsl(0_0%_15%_/_1)]';
                            if ($rowHasOutdated) {
                                $trClass .= ' plugins-row-version-mismatch';
                            }
                            ?>
                            <tr class="<?= $trClass ?>">
                                <td class="py-2 px-3 text-gray-200 font-medium sticky left-0 z-10 <?= $rowHasOutdated ? 'plugins-row-mismatch-bg' : 'bg-[hsl(0_0%_12%_/_1)]' ?>"><?= Html::encode($row['name']) ?></td>
                                <?php foreach ($servers as $s): ?>
                                    <?php
                                    $ver = $row['byServer'][$s->tag] ?? null;
                                    $verNorm = $ver !== null ? trim((string)$ver) : '';
                                    $isOutdated = $projVerNorm !== '' && $verNorm !== '' && $verNorm !== $projVerNorm;
                                    $isMissing = $projVerNorm !== '' && ($ver === null || $verNorm === '');
                                    $isRed = $isOutdated;
                                    $showUploadBtn = ($isOutdated || $isMissing) && $s->hasFtpCredentials() && $projectVer !== null;
                                    $cellClass = $isRed ? 'text-red-400' : 'text-gray-300';
                                    $cellStyle = $isRed ? ' color: #f87171;' : '';
                                    ?>
                                    <td class="py-2 px-3 <?= $cellClass ?>"<?= $cellStyle !== '' ? ' style="' . $cellStyle . '"' : '' ?>>
                                        <span class="inline-flex items-center gap-1 flex-nowrap">
                                            <?= $ver !== null ? Html::encode($ver) : '—' ?>
                                            <?php if ($showUploadBtn): ?>
                                                <button type="button" class="plugins-upload-btn ds-btn ds-btn--icon ds-btn--ghost p-0.5 rounded hover:bg-[hsl(0_0%_25%_/_1)]" title="<?= $ver === null ? 'Установить плагин по FTP' : 'Обновить плагин по FTP' ?>" data-server-tag="<?= Html::encode($s->tag) ?>" data-plugin-name="<?= Html::encode($row['name']) ?>" aria-label="<?= $ver === null ? 'Установить' : 'Обновить' ?>">
                                                    <i class="fas fa-cloud-upload-alt text-green-400" style="color: #4ade80;"></i>
                                                </button>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                <?php endforeach; ?>
                                <td class="py-2 px-3 text-green-300"><?= $projectVer !== null ? Html::encode($projectVer) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            };
            ?>
            <div id="plugins-tab-all" class="plugins-tab-pane"><?php $renderTable($pluginRows); ?></div>
            <div id="plugins-tab-not-installed" class="plugins-tab-pane hidden"><?php $renderTable($pluginRowsNotInstalled); ?></div>
        <?php endif; ?>
    </div>

    <div class="px-4 pb-4 text-xs text-gray-500">
        Версия помечена красным, если она не совпадает с актуальной в каталоге <code class="bg-[hsl(0_0%_20%_/_1)] px-1 rounded">plugins/</code> проекта. Данные кэшируются на 1 минуту.
    </div>
</div>

<style>
.plugins-table td.sticky,
.plugins-table th.sticky { box-shadow: 2px 0 4px rgba(0,0,0,0.15); }
.plugins-table tr.plugins-row-version-mismatch { background: rgba(248, 113, 113, 0.08); }
.plugins-table tr.plugins-row-version-mismatch:hover { background: rgba(248, 113, 113, 0.12); }
.plugins-table td.plugins-row-mismatch-bg { background: rgba(248, 113, 113, 0.08); }
.plugins-table tr.plugins-row-version-mismatch:hover td.plugins-row-mismatch-bg { background: rgba(248, 113, 113, 0.12); }
.plugins-tab-pane.hidden { display: none; }
.plugins-tabs .plugins-tab-btn.active { color: #fff; border-bottom-color: hsl(0 0% 40%); }
</style>

<script>
(function() {
    var uploadUrl = <?= json_encode($uploadPluginUrl) ?>;
    var csrfParam = <?= json_encode($csrfParam) ?>;
    var csrfToken = <?= json_encode($csrfToken) ?>;

    document.querySelectorAll('.plugins-tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = this.getAttribute('data-tab');
            document.querySelectorAll('.plugins-tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.plugins-tab-pane').forEach(function(p) { p.classList.add('hidden'); });
            this.classList.add('active');
            var pane = document.getElementById('plugins-tab-' + tab);
            if (pane) { pane.classList.remove('hidden'); }
        });
    });
    var firstBtn = document.querySelector('.plugins-tab-btn');
    if (firstBtn) { firstBtn.classList.add('active'); }

    document.querySelectorAll('.plugins-upload-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var serverTag = this.getAttribute('data-server-tag');
            var pluginName = this.getAttribute('data-plugin-name');
            if (!serverTag || !pluginName) return;
            this.disabled = true;
            var body = new FormData();
            body.append('server_tag', serverTag);
            body.append('plugin_name', pluginName);
            body.append(csrfParam, csrfToken);
            fetch(uploadUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var msg = data.message || 'Плагин загружен.';
                        if (data.path_full) msg += '\nПуть: ' + data.path_full;
                        alert(msg);
                        window.location.reload();
                    } else {
                        alert(data.error || 'Ошибка загрузки');
                        btn.disabled = false;
                    }
                })
                .catch(function() {
                    alert('Ошибка сети');
                    btn.disabled = false;
                });
        });
    });
})();
</script>
