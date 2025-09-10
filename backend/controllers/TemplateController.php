<?php
namespace backend\controllers;

use common\components\queue\process\TranslateJob;
use common\components\template\DbTemplateService;
use Yii;
use yii\base\BaseObject;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\components\helpers\Role;
use common\models\template\Template;
use common\models\template\TemplateFile;
use yii\helpers\FileHelper;

class TemplateController extends Controller
{
    /** ДОПУСТИМЫЕ РАСШИРЕНИЯ */
    private const SUPPORTED_EXTS = ['php','twig','scss','js'];

    /**
     * Регистр корней, которые показываем в левом дереве.
     * key => [label, pathAlias]
     */
    private const ROOTS = [
        'frontend_views'         => ['label' => '@frontend/views',                        'alias' => '@frontend/views'],
        'common_views'           => ['label' => '@common/views',                          'alias' => '@common/views'],
        'frontend_sources_css'   => ['label' => '@frontend/assets/sources/css/design',    'alias' => '@frontend/assets/sources/css/design'],
        'frontend_sources_js'    => ['label' => '@frontend/assets/sources/js',            'alias' => '@frontend/assets/sources/js'],
    ];

    /** Точки входа SCSS */
    private const SCSS_ENTRY            = '@frontend/assets/sources/css/design/styles.scss'; // fallback к файлу
    private const SCSS_OUTPUT           = '@frontend/assets/sources/css/design/styles-local.min.css';

    /** Где в БД лежит entry-файл */
    private const SCSS_ENTRY_ROOT_KEY   = 'frontend_sources_css'; // см. ROOTS
    private const SCSS_ENTRY_REL_PATH   = 'styles.scss';          // относительный путь в корне ROOTS[frontend_sources_css]

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'save-file' => ['POST'],
                    'revert-file' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Главная: всегда показывает дерево FS.
     * Если в БД нет Template — создаём "Default".
     */
    public function actionIndex($templateId = null)
    {
        $selected = $this->ensureSelectedTemplate($templateId);

        $trees = [];
        $rootSections = [];
        foreach (self::ROOTS as $key => $def) {
            $rootSections[] = ['key' => $key, 'label' => $def['label']];
            $path = $this->getRootPath($key);
            $trees[$key] = $path && is_dir($path) ? $this->buildTree($path, $key, $path) : [];
        }

        // debug
        $debug = [];
        foreach (self::ROOTS as $key => $def) {
            $alias = $def['alias'];
            $resolved = Yii::getAlias($alias, false);
            $debug[] = [
                'alias'  => $def['label'],
                'path'   => $resolved ?: '(alias not defined)',
                'exists' => ($resolved && is_dir($resolved)) ? 'yes' : 'no',
                'count'  => isset($trees[$key]) ? count($trees[$key]) : 0,
            ];
        }

        $templates = Template::find()->orderBy(['id' => SORT_ASC])->all();

        return $this->render('index', [
            'templates'        => $templates,
            'selectedTemplate' => $selected,
            'trees'            => $trees,
            'rootSections'     => $rootSections,
            'debug'            => $debug,
        ]);
    }

    /** Настройки Template */
    public function actionSettings($id)
    {
        $template = Template::findOne((int)$id);
        if (!$template) {
            throw new NotFoundHttpException('Template not found');
        }

        if ($template->load(Yii::$app->request->post()) && $template->save()) {
            Yii::$app->session->setFlash('success', 'Template saved successfully');
            return $this->redirect(['index', 'templateId' => (int)$template->id]);
        }

        return $this->render('settings', ['template' => $template]);
    }

    /** Создание Template */
    public function actionCreate()
    {
        $template = new Template();

        if ($template->load(Yii::$app->request->post()) && $template->save()) {
            Yii::$app->session->setFlash('success', 'Template created successfully');
            return $this->redirect(['index', 'templateId' => (int)$template->id]);
        }

        return $this->render('create', ['template' => $template]);
    }

    /** AJAX: загрузка содержимого файла (DB override > FS) */
    public function actionLoadFile($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $template = $this->ensureTemplateByIdOrDefault($id);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not available'];
        }

        $rootKey   = Yii::$app->request->get('root'); // один из ключей self::ROOTS
        $pathParam = Yii::$app->request->get('path'); // относительный путь от корня rootKey

        if (!isset(self::ROOTS[$rootKey])) {
            return ['success' => false, 'message' => 'Invalid root'];
        }

        $path = $this->normalizePath($pathParam);
        if ($path === null || !$this->isSupportedExt($path)) {
            return ['success' => false, 'message' => 'Invalid path/ext'];
        }

        // безопасно ищем оверрайд; если таблицы нет — вернёт null
        $override = $this->safeFindOverride($template->id, $rootKey, $path);

        $from = 'db';
        $content = $override ? $override->content : null;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($content === null) {
            $from = 'fs';
            $absRoot = $this->getRootPath($rootKey);
            if ($absRoot === null) {
                return ['success' => false, 'message' => 'Root not resolved'];
            }
            $abs = $this->absolutePath($absRoot, $path);
            if (!is_file($abs)) {
                return ['success' => false, 'message' => 'File not found on disk'];
            }
            $content = @file_get_contents($abs);
            if ($content === false) {
                return ['success' => false, 'message' => 'Unable to read file'];
            }
        }

        return [
            'success' => true,
            'from'    => $from,
            'ext'     => $ext,
            'content' => $content,
        ];
    }

    /** AJAX: сохранение (создаёт Template/TemplateFile при необходимости) + SCSS компиляция */
    public function actionSaveFile($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $template = $this->ensureTemplateByIdOrDefault($id, true);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not available'];
        }

        $rootKey   = Yii::$app->request->post('root');
        $pathParam = Yii::$app->request->post('path');
        $content   = (string)Yii::$app->request->post('content');

        if (!isset(self::ROOTS[$rootKey])) {
            return ['success' => false, 'message' => 'Invalid root'];
        }

        $path = $this->normalizePath($pathParam);
        if ($path === null || !$this->isSupportedExt($path)) {
            return ['success' => false, 'message' => 'Invalid path/ext'];
        }

        $model = TemplateFile::findOne([
                                           'template_id' => (int)$template->id,
                                           'root_alias'  => $rootKey,
                                           'path'        => $path,
                                       ]);

        if ($model === null) {
            $model = new TemplateFile();
            $model->template_id = (int)$template->id;
            $model->root_alias  = $rootKey;
            $model->path        = $path;
            $model->ext         = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }

        $model->content    = $content;
        $model->updated_by = Yii::$app->user->id ? (int)Yii::$app->user->id : null;

        if (!$model->save()) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $model->errors];
        }

        /** @var DbTemplateService $svc */
        $svc = Yii::$app->get('dbTemplates');
        $svc->updateOne((int)$template->id, $rootKey, $path, $model->ext, $content);
        $svc->bumpVersion((int)$template->id); // чтобы ?v= поменялся
        \Yii::$app->queueProcess->push(new TranslateJob());

        $resp = ['success' => true, 'message' => 'Saved'];

        // Если сохраняли SCSS — компилируем entry → output (entry берём из БД, если есть)
        if ($model->ext === 'scss') {
            $compile = $this->compileDesignScss((int)$template->id);
            $resp['compile'] = $compile;
        }

        // Очистить кэш ассетов
        $resp['assetsCleared'] = $this->clearAssetsCache();

        return $resp;
    }

    /** AJAX: удалить оверрайд (вернуться к FS) */
    public function actionRevertFile($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $template = $this->ensureTemplateByIdOrDefault($id);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not available'];
        }

        $rootKey   = Yii::$app->request->post('root');
        $pathParam = Yii::$app->request->post('path');

        if (!isset(self::ROOTS[$rootKey])) {
            return ['success' => false, 'message' => 'Invalid root'];
        }

        $path = $this->normalizePath($pathParam);
        if ($path === null || !$this->isSupportedExt($path)) {
            return ['success' => false, 'message' => 'Invalid path/ext'];
        }

        $model = TemplateFile::findOne([
                                           'template_id' => (int)$template->id,
                                           'root_alias'  => $rootKey,
                                           'path'        => $path,
                                       ]);

        if ($model && $model->delete()) {
            return ['success' => true, 'message' => 'Reverted'];
        }

        /** @var DbTemplateService $svc */
        $svc = Yii::$app->get('dbTemplates');
        $svc->removeOne((int)$template->id, $rootKey, $path, strtolower(pathinfo($path, PATHINFO_EXTENSION)));
        $svc->bumpVersion((int)$template->id);

        $this->clearAssetsCache();

        return ['success' => false, 'message' => 'Override not found'];
    }

    // ================== Helpers ==================

    /** Безопасный поиск оверрайда: если таблицы нет — вернёт null */
    private function safeFindOverride($templateId, $rootKey, $path)
    {
        try {
            return TemplateFile::findOne([
                                             'template_id' => (int)$templateId,
                                             'root_alias'  => $rootKey,
                                             'path'        => $path,
                                         ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Компиляция SCSS entry → output (scssphp или sass CLI).
     * Источник для entry: сначала БД (TemplateFile c root=frontend_sources_css, path=styles.scss), потом файл.
     */
    private function compileDesignScss($templateId)
    {
        $out = Yii::getAlias(self::SCSS_OUTPUT, false);
        if ($out === false) {
            return ['success' => false, 'message' => 'SCSS output alias not resolved: ' . self::SCSS_OUTPUT];
        }

        // Абсолютные пути для импортов
        $designDir = $this->getRootPath(self::SCSS_ENTRY_ROOT_KEY); // @frontend/assets/sources/css/design
        if ($designDir === null) {
            return ['success' => false, 'message' => 'Design dir alias not resolved: ' . self::ROOTS[self::SCSS_ENTRY_ROOT_KEY]['alias']];
        }
        $cssRootDir = dirname($designDir); // .../css

        // 1) Готовим каталог вывода
        $outDir = dirname($out);
        if (!is_dir($outDir)) {
            try { FileHelper::createDirectory($outDir, 0775, true); }
            catch (\Throwable $e) { return ['success' => false, 'message' => 'Cannot create output dir: ' . $outDir . ' — ' . $e->getMessage()]; }
        }
        if (!is_writable($outDir)) {
            return ['success' => false, 'message' => 'Output dir is not writable: ' . $outDir];
        }

        // 2) Забираем entry: из БД → иначе из файла
        $scssCode = null;
        $entryVirtualName = null;

        $override = $this->safeFindOverride($templateId, self::SCSS_ENTRY_ROOT_KEY, self::SCSS_ENTRY_REL_PATH);
        if ($override && $override->content !== null && $override->content !== '') {
            $scssCode = $override->content;
            $entryVirtualName = Yii::getAlias(self::SCSS_ENTRY, false) ?: 'db://styles.scss';
        }
        if ($scssCode === null) {
            $in = Yii::getAlias(self::SCSS_ENTRY, false);
            if ($in === false || !is_file($in)) {
                return ['success' => false, 'message' => 'SCSS entry not found (DB nor FS): ' . self::SCSS_ENTRY];
            }
            $scssCode = @file_get_contents($in);
            if ($scssCode === false) {
                return ['success' => false, 'message' => 'Cannot read SCSS entry: ' . $in];
            }
            $entryVirtualName = $in;
        }

        // 3) Пытаемся как SCSS через scssphp
        $css = null;
        if (class_exists('\ScssPhp\ScssPhp\Compiler')) {
            try {
                $compiler = new \ScssPhp\ScssPhp\Compiler();
                if (class_exists('\ScssPhp\ScssPhp\OutputStyle') && method_exists($compiler, 'setOutputStyle')) {
                    $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);
                } elseif (class_exists('\ScssPhp\ScssPhp\Formatter\Compressed') && method_exists($compiler, 'setFormatter')) {
                    $compiler->setFormatter('\ScssPhp\ScssPhp\Formatter\Compressed');
                }
                // Пути импортов
                $importPaths = [$designDir, $cssRootDir];
                if (method_exists($compiler, 'setImportPaths')) {
                    $compiler->setImportPaths($importPaths);
                } elseif (method_exists($compiler, 'addImportPath')) {
                    foreach ($importPaths as $p) { $compiler->addImportPath($p); }
                }

                if (method_exists($compiler, 'compileString')) {
                    $css = $compiler->compileString($scssCode, $entryVirtualName)->getCss();
                } else {
                    $css = $compiler->compile($scssCode, $entryVirtualName);
                }
            } catch (\Throwable $e) {
                $css = null; // перейдём к CLI
            }
        }

        // 4) Фолбэк: dart-sass (CLI). Сначала как SCSS, если не вышло — как Sass (.sass).
        if ($css === null) {
            $dbSourceUsed = ($override && $override->content !== null && $override->content !== '');
            $tmpFiles = [];

            // вспомогательная функция для запуска CLI
            $runCli = function ($entryFile) use ($designDir, $cssRootDir, $out) {
                $cmd = 'sass --no-source-map --style=compressed '
                    . '--load-path ' . escapeshellarg($designDir) . ' '
                    . '--load-path ' . escapeshellarg($cssRootDir) . ' '
                    . escapeshellarg($entryFile) . ' ' . escapeshellarg($out) . ' 2>&1';
                $output = [];
                $code = null;
                @exec($cmd, $output, $code);
                return [$code, $output];
            };

            // a) пробуем SCSS-файл
            $entryFile = $dbSourceUsed ? ($designDir . DIRECTORY_SEPARATOR . '__db_styles_tmp.scss')
                : (Yii::getAlias(self::SCSS_ENTRY, false));
            if ($dbSourceUsed) {
                @unlink($entryFile);
                if (@file_put_contents($entryFile, $scssCode) === false) {
                    return ['success' => false, 'message' => 'Cannot write temp SCSS for CLI: ' . $entryFile];
                }
                $tmpFiles[] = $entryFile;
            }

            [$code, $output] = $runCli($entryFile);

            // b) если не получилось и источник из БД — пробуем как .sass (индентид синтаксис)
            if ($code !== 0 && $dbSourceUsed) {
                $entryFileSass = $designDir . DIRECTORY_SEPARATOR . '__db_styles_tmp.sass';
                @unlink($entryFileSass);
                if (@file_put_contents($entryFileSass, $scssCode) === false) {
                    // уберём предыдущий tmp и вернём ошибку
                    foreach ($tmpFiles as $f) { @unlink($f); }
                    return ['success' => false, 'message' => 'Cannot write temp Sass for CLI: ' . $entryFileSass];
                }
                $tmpFiles[] = $entryFileSass;

                [$code2, $output2] = $runCli($entryFileSass);

                // убираем tmp-файлы
                foreach ($tmpFiles as $f) { @unlink($f); }

                if ($code2 === 0) {
                    return ['success' => true, 'message' => 'Sass compiled (sass CLI, indented syntax)', 'compiler' => 'sass'];
                }

                return [
                    'success'  => false,
                    'message'  => 'SCSS/Sass compile failed. CLI output: ' . implode("\n", (array)$output) . "\n" . implode("\n", (array)$output2),
                    'compiler' => null,
                ];
            }

            // убираем tmp-файлы
            foreach ($tmpFiles as $f) { @unlink($f); }

            if ($code === 0) {
                return ['success' => true, 'message' => 'SCSS compiled (sass CLI)', 'compiler' => 'sass'];
            }

            return [
                'success'  => false,
                'message'  => 'SCSS compile failed. CLI output: ' . implode("\n", (array)$output),
                'compiler' => null,
            ];
        }

        // 5) Пишем CSS атомарно
        $outDir = dirname($out);
        $tmp = @tempnam($outDir, 'scss-');
        if ($tmp === false) {
            return ['success' => false, 'message' => 'Cannot create temp file in: ' . $outDir];
        }
        if (@file_put_contents($tmp, $css) === false) {
            @unlink($tmp);
            return ['success' => false, 'message' => 'Cannot write CSS temp file: ' . $tmp];
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $out)) {
            @unlink($tmp);
            if (@file_put_contents($out, $css) === false) {
                return ['success' => false, 'message' => 'Cannot write CSS output: ' . $out];
            }
        }
        return ['success' => true, 'message' => 'SCSS compiled (scssphp)', 'compiler' => 'scssphp'];
    }

    /** Возвращает выбранный Template, создавая default при отсутствии любых записей */
    private function ensureSelectedTemplate($templateId)
    {
        if ($templateId) {
            $t = Template::findOne((int)$templateId);
            if ($t) return $t;
        }
        $first = Template::find()->orderBy(['id' => SORT_ASC])->one();
        if ($first) return $first;

        $t = new Template();
        $t->name = 'Default';
        if ($t->save(false)) {
            return $t;
        }
        return $first;
    }

    private function ensureTemplateByIdOrDefault($id, $createIfMissing = false)
    {
        $tpl = Template::findOne((int)$id);
        if ($tpl) return $tpl;

        $first = Template::find()->orderBy(['id' => SORT_ASC])->one();
        if ($first) return $first;

        if ($createIfMissing) {
            $t = new Template();
            $t->name = 'Default';
            if ($t->save(false)) return $t;
        }
        return null;
    }

    /** Разрешить путь для корня по ключу */
    private function getRootPath($key)
    {
        if (!isset(self::ROOTS[$key])) return null;
        $alias = self::ROOTS[$key]['alias'];
        $path  = Yii::getAlias($alias, false);
        if ($path === false || !is_dir($path)) return null;
        return $path;
    }

    /** Нормализация относительного пути */
    private function normalizePath($path)
    {
        if ($path === null) return null;
        $path = trim(str_replace('\\','/',$path), '/');
        if ($path === '' || strpos($path, '..') !== false) return null;
        return $path;
    }

    /** Проверка расширения */
    private function isSupportedExt($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::SUPPORTED_EXTS, true);
    }

    /** Склейка абсолютного пути */
    private function absolutePath($absRoot, $relPath)
    {
        return rtrim($absRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    }

    /**
     * Рекурсивно строит дерево: показывает все папки, а файлы — только с поддерживаемыми расширениями.
     */
    private function buildTree($absRoot, $rootKey, $currentDir)
    {
        $nodes = [];
        $entries = @scandir($currentDir);
        if ($entries === false) $entries = [];

        $dirs = [];
        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $abs = $currentDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($abs)) {
                $dirs[] = $entry;
            } else {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($ext, self::SUPPORTED_EXTS, true)) {
                    $files[] = $entry;
                }
            }
        }

        usort($dirs, function($a,$b){ return strcmp(mb_strtolower($a), mb_strtolower($b)); });
        usort($files,function($a,$b){ return strcmp(mb_strtolower($a), mb_strtolower($b)); });

        foreach ($dirs as $d) {
            $childAbs = $currentDir . DIRECTORY_SEPARATOR . $d;
            $children = $this->buildTree($absRoot, $rootKey, $childAbs);
            $nodes[] = [
                'type' => 'dir',
                'name' => $d,
                'children' => $children,
            ];
        }

        foreach ($files as $f) {
            $abs = $currentDir . DIRECTORY_SEPARATOR . $f;
            $rel = ltrim(str_replace($absRoot, '', $abs), DIRECTORY_SEPARATOR);
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $nodes[] = [
                'type' => 'file',
                'name' => $f,
                'path' => str_replace(DIRECTORY_SEPARATOR, '/', $rel),
                'ext'  => $ext,
                'root' => $rootKey,
            ];
        }

        return $nodes;
    }

    /** Очистить кэш ассетов (frontend/backed + runtime), безопасно пересоздав каталоги */
    private function clearAssetsCache(): array
    {
        $dirs = [
            '@frontend/web/assets',
            '@backend/web/assets',
        ];

        $report = [];
        foreach ($dirs as $alias) {
            $path = Yii::getAlias($alias, false);
            if ($path === false || !is_dir($path)) {
                $report[] = ['dir' => (string)$alias, 'skipped' => true];
                continue;
            }
            try {
                // Полностью удаляем каталог и создаём заново — это быстрее и чище
                FileHelper::removeDirectory($path);
                FileHelper::createDirectory($path, 0775, true);
                $report[] = ['dir' => $path, 'cleared' => true];
            } catch (\Throwable $e) {
                $report[] = ['dir' => $path, 'cleared' => false, 'error' => $e->getMessage()];
            }
        }
        // Сбросим внутренний кеш AssetManager (на случай долгоживущих процессов)
        if (isset(Yii::$app->assetManager)) {
            Yii::$app->assetManager->forceCopy = true; // на один запрос точно перепубликует
        }

        $cur = (string)(Yii::$app->settings->get('site_version') ?: '0');
        if (function_exists('bcadd')) {
            $new = bcadd($cur, '0.00001', 5);           // 5 знаков после запятой
        } else {
            // fallback, если bcmath не установлен
            $new = number_format(((float)$cur + 0.00001), 5, '.', '');
        }
        Yii::$app->settings->set('site_version', $new);

        return $report;
    }
}
