<?php
namespace common\components\template;

use Yii;
use yii\base\Component;
use yii\base\View;
use yii\helpers\FileHelper;
use common\models\template\TemplateFile;

class DbTemplateService extends Component
{
    // где держим материализованный кэш
    public $viewsBase  = '@frontend/runtime/dbviews';
    public $assetsBase = '@frontend/runtime/dbassets';

    // корни, которые считаем "views" (подключим через theme->pathMap)
    public $viewRoots = [
        'frontend_views' => '@frontend/views',
        'common_views'   => '@common/views',
    ];

    // корни ассетов (пока используем js)
    public $assetRoots = [
        'frontend_sources_js' => '@frontend/assets/sources/js',
        // на будущее: 'frontend_sources_css' => '@frontend/assets/sources/css',
    ];

    /** Применить кэш для выбранного templateId: подключить pathMap и гарантировать, что кэш прогрет (один раз). */
    public function applyFor(View $view, int $templateId): void
    {
        $viewsBase = Yii::getAlias($this->viewsBase) . "/{$templateId}";
        $this->ensureViewsCacheWarmed($templateId, $viewsBase);

        // Включаем pathMap: сначала кэш, затем обычные пути
        $map = $view->theme ? $view->theme->pathMap : [];
        foreach ($this->viewRoots as $rootKey => $alias) {
            $fsRoot = Yii::getAlias($alias, false);
            if ($fsRoot === false) { continue; }
            $rootCacheDir = $viewsBase . '/' . $rootKey;
            $map[$fsRoot] = array_merge([$rootCacheDir], isset($map[$fsRoot]) ? (array)$map[$fsRoot] : []);
        }
        if ($view->theme) {
            $view->theme->pathMap = $map;
        } else {
            $view->theme = Yii::createObject(['class' => \yii\base\Theme::class, 'pathMap' => $map]);
        }
    }

    /** Версия шаблона (для ?v= в URL). */
    public function getVersion(int $templateId): int
    {
        $cache = Yii::$app->cache;
        $key = "tplver:{$templateId}";
        $ver = (int)$cache->get($key);
        if ($ver <= 0) { $ver = 1; $cache->set($key, $ver); }
        return $ver;
    }

    /** Бамп версии (зовём в контроллере после сохранения/отката). */
    public function bumpVersion(int $templateId): void
    {
        $cache = Yii::$app->cache;
        $key = "tplver:{$templateId}";
        $ver = (int)$cache->get($key);
        if ($ver <= 0) $ver = 1;
        $cache->set($key, $ver + 1);
    }

    /** Прогреть кэш вьюх один раз (создать файлы, если их ещё нет). */
    protected function ensureViewsCacheWarmed(int $templateId, string $viewsBase): void
    {
        $marker = $viewsBase . '/.ready';
        if (is_file($marker)) return;

        foreach ($this->viewRoots as $rootKey => $alias) {
            $this->dumpOverrides($templateId, $rootKey, $viewsBase . '/' . $rootKey, ['php','twig']);
        }
        FileHelper::createDirectory($viewsBase, 0775, true);
        @file_put_contents($marker, date('c'));
    }

    /** Материализовать/обновить один файл в кэше (зовём при сохранении). */
    public function updateOne(int $templateId, string $rootKey, string $path, string $ext, ?string $content): void
    {
        // вьюхи → кладём в dbviews; js → dbassets
        if (isset($this->viewRoots[$rootKey]) && in_array($ext, ['php','twig'], true)) {
            $base = Yii::getAlias($this->viewsBase) . "/{$templateId}/{$rootKey}";
            $this->writeOne($base, $path, $content);
        } elseif (isset($this->assetRoots[$rootKey]) && in_array($ext, ['js'], true)) {
            $base = Yii::getAlias($this->assetsBase) . "/{$templateId}/{$rootKey}";
            $this->writeOne($base, $path, $content);
        }
    }

    /** Удалить файл из кэша при откате. */
    public function removeOne(int $templateId, string $rootKey, string $path, string $ext): void
    {
        if (isset($this->viewRoots[$rootKey]) && in_array($ext, ['php','twig'], true)) {
            $base = Yii::getAlias($this->viewsBase) . "/{$templateId}/{$rootKey}";
            $abs  = $base . '/' . trim($path, '/');
            @unlink($abs);
        } elseif (isset($this->assetRoots[$rootKey]) && in_array($ext, ['js'], true)) {
            $base = Yii::getAlias($this->assetsBase) . "/{$templateId}/{$rootKey}";
            $abs  = $base . '/' . trim($path, '/');
            @unlink($abs);
        }
    }

    /** Слить все оверрайды конкретного root в каталог. */
    protected function dumpOverrides(int $templateId, string $rootKey, string $targetRoot, array $exts): void
    {
        try {
            $rows = TemplateFile::find()
                                ->select(['path','ext','content'])
                                ->where(['template_id'=>$templateId,'root_alias'=>$rootKey])
                                ->andWhere(['in','ext',$exts])
                                ->asArray()->all();
        } catch (\Throwable $e) {
            return; // таблицы нет — тихо
        }
        foreach ($rows as $r) {
            $this->writeOne($targetRoot, $r['path'], $r['content']);
        }
    }

    protected function writeOne(string $baseDir, string $relPath, ?string $content): void
    {
        $rel = trim(str_replace('\\','/',$relPath), '/');
        $abs = rtrim($baseDir,'/') . '/' . $rel;
        $dir = dirname($abs);
        if (!is_dir($dir)) { FileHelper::createDirectory($dir, 0775, true); }
        if ($content === null) $content = '';
        $tmp = @tempnam($dir, 'dbtpl-');
        if ($tmp === false) { $tmp = $abs; }
        @file_put_contents($tmp, $content);
        @chmod($tmp, 0664);
        if ($tmp !== $abs) { @rename($tmp, $abs); }
    }
}
