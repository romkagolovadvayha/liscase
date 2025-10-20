<?php
namespace common\models\template;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;

class Template extends ActiveRecord
{
    // Константы для компиляции SCSS
    private const SCSS_ENTRY            = '@frontend/assets/sources/css/design/styles.scss'; // fallback к файлу
    private const SCSS_OUTPUT           = '@frontend/assets/sources/css/design/styles-local.min.css';
    const SCSS_ENTRY_ROOT_KEY = 'frontend_sources_css';
    const SCSS_ENTRY_REL_PATH = 'design/styles.scss';
    
    const ROOTS = [
        'frontend_sources_css' => [
            'alias' => '@frontend/assets/sources/css',
            'label' => 'Frontend CSS Sources'
        ],
    ];

    public static function tableName()
    {
        return 'template';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            ['name', 'string', 'max' => 255],
        ];
    }

    /**
     * Компиляция SCSS в CSS для дизайна шаблона
     * @param int|null $templateId ID шаблона
     * @return array ['success' => bool, 'message' => string, 'compiler' => string|null]
     */
    public static function compileDesignScss($templateId = null)
    {
        $out = Yii::getAlias(self::SCSS_OUTPUT, false);
        if ($out === false) {
            return ['success' => false, 'message' => 'SCSS output alias not resolved: ' . self::SCSS_OUTPUT];
        }

        // Абсолютные пути для импортов
        $designDir = self::getRootPath(self::SCSS_ENTRY_ROOT_KEY);
        if ($designDir === null) {
            return ['success' => false, 'message' => 'Design dir alias not resolved: ' . self::ROOTS[self::SCSS_ENTRY_ROOT_KEY]['alias']];
        }
        $cssRootDir = dirname($designDir);

        // 1) Готовим каталог вывода
        $outDir = dirname($out);
        if (!is_dir($outDir)) {
            try { 
                FileHelper::createDirectory($outDir, 0775, true); 
            } catch (\Throwable $e) { 
                return ['success' => false, 'message' => 'Cannot create output dir: ' . $outDir . ' — ' . $e->getMessage()]; 
            }
        }
        if (!is_writable($outDir)) {
            return ['success' => false, 'message' => 'Output dir is not writable: ' . $outDir];
        }

        // 2) Забираем entry: из БД → иначе из файла
        $scssCode = null;
        $entryVirtualName = null;

        $override = self::safeFindOverride($templateId, self::SCSS_ENTRY_ROOT_KEY, self::SCSS_ENTRY_REL_PATH);
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

        // 4) Фолбэк: dart-sass (CLI)
        if ($css === null) {
            $dbSourceUsed = ($override && $override->content !== null && $override->content !== '');
            $tmpFiles = [];

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

            if ($code !== 0 && $dbSourceUsed) {
                $entryFileSass = $designDir . DIRECTORY_SEPARATOR . '__db_styles_tmp.sass';
                @unlink($entryFileSass);
                if (@file_put_contents($entryFileSass, $scssCode) === false) {
                    foreach ($tmpFiles as $f) { @unlink($f); }
                    return ['success' => false, 'message' => 'Cannot write temp Sass for CLI: ' . $entryFileSass];
                }
                $tmpFiles[] = $entryFileSass;

                [$code2, $output2] = $runCli($entryFileSass);
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

    /**
     * Получить абсолютный путь для root-ключа
     */
    protected static function getRootPath($rootKey)
    {
        if (!isset(self::ROOTS[$rootKey])) {
            return null;
        }
        $alias = self::ROOTS[$rootKey]['alias'];
        return Yii::getAlias($alias, false);
    }

    /**
     * Безопасный поиск override файла из БД
     */
    protected static function safeFindOverride($templateId, $rootKey, $relPath)
    {
        if (!$templateId || !class_exists('common\models\template\TemplateFile')) {
            return null;
        }
        
        try {
            return \common\models\template\TemplateFile::find()
                ->where([
                    'template_id' => $templateId,
                    'root' => $rootKey,
                    'path' => $relPath
                ])
                ->one();
        } catch (\Throwable $e) {
            return null;
        }
    }
}