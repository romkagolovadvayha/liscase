<?php
namespace backend\controllers;

use Yii;
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
        'frontend_views'         => ['label' => '@frontend/views',                 'alias' => '@frontend/views'],
        'common_views'           => ['label' => '@common/views',                   'alias' => '@common/views'],
        'frontend_sources_css'   => ['label' => '@frontend/assets/sources/css/design',    'alias' => '@frontend/assets/sources/css/design'],
        'frontend_sources_js'    => ['label' => '@frontend/assets/sources/js',            'alias' => '@frontend/assets/sources/js'],
    ];

    /** Точки входа SCSS (что компилируем при сохранении любого SCSS) */
    private const SCSS_ENTRY  = '@frontend/assets/sources/css/design/styles.scss';
    private const SCSS_OUTPUT = '@frontend/assets/sources/css/design/styles-local.min.css';

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

        // NOTE: убедитесь, что в модели TemplateFile НЕТ строгого правила 'in' для root_alias,
        // либо включены все ключи из self::ROOTS.
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

        $resp = ['success' => true, 'message' => 'Saved'];

        // Если сохраняли SCSS — компилируем entry → output
        if ($model->ext === 'scss') {
            $compile = $this->compileDesignScss();
            $resp['compile'] = $compile;
        }

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

    /** Компиляция SCSS entry → output (scssphp или sass CLI) */
    private function compileDesignScss()
    {
        $in  = Yii::getAlias(self::SCSS_ENTRY, false);
        $out = Yii::getAlias(self::SCSS_OUTPUT, false);

        if ($in === false || !is_file($in)) {
            return ['success' => false, 'message' => 'SCSS entry not found: ' . self::SCSS_ENTRY];
        }
        if ($out === false) {
            return ['success' => false, 'message' => 'SCSS output alias not resolved: ' . self::SCSS_OUTPUT];
        }

        $outDir = dirname($out);
        // 1) Создать каталог если нужно
        if (!is_dir($outDir)) {
            try {
                FileHelper::createDirectory($outDir, 0775, true);
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => 'Cannot create output dir: ' . $outDir . ' — ' . $e->getMessage()];
            }
        }
        // 2) Проверка прав
        if (!is_writable($outDir)) {
            return ['success' => false, 'message' => 'Output dir is not writable: ' . $outDir];
        }

        // ===== компилируем SCSS =====
        $css = null;
        // a) scssphp (composer: scssphp/scssphp)
        if (class_exists('\ScssPhp\ScssPhp\Compiler')) {
            try {
                $scssCode = @file_get_contents($in);
                if ($scssCode === false) {
                    return ['success' => false, 'message' => 'Cannot read SCSS entry: ' . $in];
                }
                $compiler = new \ScssPhp\ScssPhp\Compiler();

                if (class_exists('\ScssPhp\ScssPhp\OutputStyle') && method_exists($compiler, 'setOutputStyle')) {
                    $compiler->setOutputStyle(\ScssPhp\ScssPhp\OutputStyle::COMPRESSED);
                } elseif (class_exists('\ScssPhp\ScssPhp\Formatter\Compressed') && method_exists($compiler, 'setFormatter')) {
                    $compiler->setFormatter('\ScssPhp\ScssPhp\Formatter\Compressed');
                }

                if (method_exists($compiler, 'compileString')) {
                    $css = $compiler->compileString($scssCode, $in)->getCss();
                } else {
                    $css = $compiler->compile($scssCode, $in);
                }
            } catch (\Throwable $e) {
                // если упало — пойдём на sass CLI
                $css = null;
            }
        }

        // b) sass CLI (dart-sass): если нет scssphp или scssphp упал
        if ($css === null) {
            $cmd = 'sass --no-source-map --style=compressed ' . escapeshellarg($in) . ' ' . escapeshellarg($out) . ' 2>&1';
            $output = [];
            $code = null;
            @exec($cmd, $output, $code);
            if ($code === 0) {
                return ['success' => true, 'message' => 'SCSS compiled (sass CLI)', 'compiler' => 'sass'];
            }
            return [
                'success'  => false,
                'message'  => 'SCSS compile failed. Install scssphp/scssphp (Composer) or sass (CLI). CLI output: ' . implode("\n", (array)$output),
                'compiler' => null,
            ];
        }

        // ===== пишем атомарно =====
        $tmp = @tempnam($outDir, 'scss-');
        if ($tmp === false) {
            return ['success' => false, 'message' => 'Cannot create temp file in: ' . $outDir];
        }
        $ok = @file_put_contents($tmp, $css);
        if ($ok === false) {
            @unlink($tmp);
            return ['success' => false, 'message' => 'Cannot write CSS temp file: ' . $tmp];
        }
        @chmod($tmp, 0664);

        // Переименуем поверх (атомарно в пределах одного FS)
        if (!@rename($tmp, $out)) {
            // fallback: прямая запись
            @unlink($tmp);
            $ok2 = @file_put_contents($out, $css);
            if ($ok2 === false) {
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
}
