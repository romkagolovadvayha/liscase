<?php

namespace backend\controllers;

use backend\components\BackendController;
use backend\components\FtpHelper;
use backend\components\PluginsComparisonHelper;
use common\components\helpers\Role;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Сравнение версий плагинов на серверах (o.plugins) с версиями в репозитории.
 */
class PluginsController extends BackendController
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'upload-plugin' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Список плагинов: сравнение версий по серверам + актуальная версия из plugins/.
     */
    public function actionIndex()
    {
        $cacheKey = PluginsComparisonHelper::CACHE_KEY_PLUGINS_DATA;
        $data = Yii::$app->cache->get($cacheKey);

        if ($data === false) {
            $data = $this->fetchPluginsData();
            Yii::$app->cache->set($cacheKey, $data, PluginsComparisonHelper::CACHE_TTL);
        }

        $this->view->params['contentClass'] = 'content-no-padding';
        $this->view->params['showFilters'] = false;

        return $this->render('index', [
            'servers' => $data['servers'],
            'pluginRows' => $data['pluginRows'],
            'pluginRowsNotInstalled' => $data['pluginRowsNotInstalled'],
            'projectVersions' => $data['projectVersions'],
            'cachedAt' => $data['cachedAt'] ?? null,
        ]);
    }

    /**
     * Обновить данные (сброс кэша и редирект).
     */
    public function actionRefresh()
    {
        Yii::$app->cache->delete(PluginsComparisonHelper::CACHE_KEY_PLUGINS_DATA);
        return $this->redirect(['index']);
    }

    /**
     * Загрузить плагин из проекта на сервер по FTP в oxide/plugins.
     * POST: server_tag, plugin_name
     */
    public function actionUploadPlugin()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $serverTag = trim((string)(Yii::$app->request->post('server_tag') ?? ''));
        $pluginName = trim((string)(Yii::$app->request->post('plugin_name') ?? ''));
        if ($serverTag === '' || $pluginName === '') {
            return ['success' => false, 'error' => 'Укажите сервер и плагин'];
        }
        $server = Servers::findOne(['tag' => $serverTag]);
        if (!$server || !$server->hasFtpCredentials()) {
            return ['success' => false, 'error' => 'Сервер не найден или FTP не настроен'];
        }
        $localPath = PluginsComparisonHelper::resolvePluginFilePath($pluginName);
        if ($localPath === null || !is_file($localPath)) {
            return ['success' => false, 'error' => 'Плагин не найден в проекте'];
        }
        $fileName = basename($localPath);
        $remotePath = 'oxide/plugins/' . $fileName;
        $helper = new FtpHelper($server);
        if (!$helper->connect()) {
            return ['success' => false, 'error' => 'Не удалось подключиться к FTP'];
        }
        try {
            if ($helper->upload($localPath, $remotePath)) {
                Yii::$app->cache->delete(PluginsComparisonHelper::CACHE_KEY_PLUGINS_DATA);
                $ftpRoot = $server->getFtpRootPath();
                $fullPath = $ftpRoot !== '' ? rtrim($ftpRoot, '/') . '/' . $remotePath : $remotePath;
                return [
                    'success' => true,
                    'message' => 'Плагин загружен.',
                    'path_relative' => $remotePath,
                    'path_full' => $fullPath,
                ];
            }
            return ['success' => false, 'error' => 'Ошибка загрузки файла по FTP. Путь: ' . $remotePath . ' (относительно корня FTP). Проверьте настройки FTP сервера и корневой каталог.'];
        } finally {
            $helper->disconnect();
        }
    }

    private function fetchPluginsData(): array
    {
        $servers = Servers::find()
            ->andWhere(['status' => Servers::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $serverTags = array_map(function (Servers $s) {
            return $s->tag;
        }, $servers);

        $results = [];
        if (!empty($serverTags)) {
            $results = RconTasks::executeWithResults('o.plugins', $serverTags);
        }

        $byServer = [];
        $allPluginNames = [];
        foreach ($results as $tag => $result) {
            if (isset($result['error']) && $result['error']) {
                $byServer[$tag] = [];
                continue;
            }
            $raw = $result['result'] ?? '';
            if (is_array($raw)) {
                $output = $raw['output'] ?? $raw['message'] ?? implode("\n", $raw);
            } else {
                $output = (string)$raw;
                // RCON может вернуть JSON: {"success":true,"result":"Listing 24 plugins:\n..."}
                if (isset($output[0]) && $output[0] === '{') {
                    $decoded = json_decode($output, true);
                    if (is_array($decoded) && isset($decoded['result'])) {
                        $output = is_string($decoded['result']) ? $decoded['result'] : json_encode($decoded['result']);
                    }
                }
            }
            $parsed = PluginsComparisonHelper::parseOPluginsOutput($output);
            $byServer[$tag] = $parsed;
            $allPluginNames = array_merge($allPluginNames, array_keys($parsed));
        }
        $projectVersions = PluginsComparisonHelper::getProjectPluginVersions();
        $allPluginNames = array_unique(array_merge($allPluginNames, array_keys($projectVersions)));
        sort($allPluginNames);
        $projectVersionsByNormalized = [];
        foreach ($projectVersions as $pName => $pVer) {
            $key = strtolower(preg_replace('/\s++/', ' ', trim($pName)));
            if ($key !== '' && !isset($projectVersionsByNormalized[$key])) {
                $projectVersionsByNormalized[$key] = $pVer;
            }
        }

        $pluginRows = [];
        foreach ($allPluginNames as $name) {
            $projectVer = $projectVersions[$name] ?? $projectVersionsByNormalized[strtolower(preg_replace('/\s++/', ' ', trim($name)))] ?? null;
            $row = [
                'name' => $name,
                'byServer' => [],
                'projectVersion' => $projectVer,
            ];
            foreach (array_keys($byServer) as $tag) {
                $row['byServer'][$tag] = $byServer[$tag][$name] ?? null;
            }
            $pluginRows[] = $row;
        }

        $projVerNorm = static function ($v) {
            return $v !== null ? trim((string)$v) : '';
        };
        // Несовпадение версии: на каком-то сервере стоит версия, отличная от проектной (просто отсутствие не считаем)
        $hasMismatch = function ($row) use ($projVerNorm) {
            $pv = $projVerNorm($row['projectVersion'] ?? null);
            if ($pv === '') {
                return false;
            }
            foreach ($row['byServer'] as $ver) {
                $sv = $projVerNorm($ver);
                if ($sv !== '' && $sv !== $pv) {
                    return true;
                }
            }
            return false;
        };
        // В «Не установленные» только плагины из проекта (есть в Актуальная), которых нет ни на одном сервере
        $hasNotInstalledOnAnyServer = function ($row) use ($projVerNorm) {
            $pv = $projVerNorm($row['projectVersion'] ?? null);
            if ($pv === '') {
                return false;
            }
            foreach ($row['byServer'] as $ver) {
                if ($ver !== null && trim((string)$ver) !== '') {
                    return false;
                }
            }
            return true;
        };

        usort($pluginRows, function ($a, $b) use ($hasMismatch) {
            $aBad = $hasMismatch($a);
            $bBad = $hasMismatch($b);
            if ($aBad !== $bBad) {
                return $aBad ? -1 : 1;
            }
            return strcmp($a['name'], $b['name']);
        });

        $pluginRowsNotInstalled = array_values(array_filter($pluginRows, $hasNotInstalledOnAnyServer));
        $pluginRowsInstalled = array_values(array_filter($pluginRows, function ($row) use ($hasNotInstalledOnAnyServer) {
            return !$hasNotInstalledOnAnyServer($row);
        }));

        return [
            'servers' => $servers,
            'pluginRows' => $pluginRowsInstalled,
            'pluginRowsNotInstalled' => $pluginRowsNotInstalled,
            'projectVersions' => $projectVersions,
            'cachedAt' => time(),
        ];
    }
}
