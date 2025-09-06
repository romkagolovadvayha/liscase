<?php
namespace frontend\controllers;

use common\components\template\DbTemplateService;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use common\models\template\TemplateFile;

class DbAssetController extends Controller
{
    private const ROOTS = [
        'frontend_sources_js' => '@frontend/assets/sources/js',
    ];

    public function actionServe($root, $path, $templateId = null, $v = null)
    {
        /** @var DbTemplateService $svc */
        $svc = Yii::$app->get('dbTemplates');

        $templateId = (int)($templateId ?: (Yii::$app->params['activeTemplateId'] ?? 1));
        $path = trim(str_replace('\\','/',$path), '/');
        if ($path === '' || strpos($path, '..') !== false) {
            throw new NotFoundHttpException('Bad path');
        }
        if (!isset(self::ROOTS[$root])) {
            throw new NotFoundHttpException('Bad root');
        }

        // 1) Пробуем из кэша на диске (@runtime/dbassets/{tid}/{root}/path)
        $cacheBase = Yii::getAlias('@runtime/dbassets') . "/{$templateId}/{$root}";
        $absCache  = $cacheBase . '/' . $path;
        if (is_file($absCache)) {
            return $this->sendFileFast($absCache);
        }

        // 2) Нет кэша — попробуем из БД и сразу материализуем (чтоб в следующий раз было мгновенно)
        try {
            $row = TemplateFile::find()
                               ->where(['template_id'=>$templateId,'root_alias'=>$root,'path'=>$path])
                               ->one();
        } catch (\Throwable $e) { $row = null; }

        if ($row && $row->content !== null) {
            $svc->updateOne($templateId, $root, $path, 'js', $row->content);
            return $this->rawJs($row->content);
        }

        // 3) Файл с диска
        $base = Yii::getAlias(self::ROOTS[$root], false);
        $abs  = $base ? $base . DIRECTORY_SEPARATOR . str_replace('/','\\',$path) : null;
        if (!$abs || !is_file($abs)) {
            throw new NotFoundHttpException('Asset not found');
        }
        return Yii::$app->response->sendFile($abs, null, ['inline' => true]);
    }

    private function rawJs(string $content)
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        $r = Yii::$app->response;
        $r->headers->set('Content-Type', 'application/javascript; charset=UTF-8');
        $r->headers->set('Cache-Control', 'public, max-age=31536000, immutable'); // из-за ?v=
        $r->content = $content;
        return $r;
    }

    private function sendFileFast(string $abs)
    {
        $etag = '"' . md5_file($abs) . '"';
        $lastMod = gmdate('D, d M Y H:i:s', filemtime($abs)) . ' GMT';
        $req = Yii::$app->request;

        if ($req->headers->get('If-None-Match') === $etag ||
            strtotime($req->headers->get('If-Modified-Since')) >= filemtime($abs)) {
            Yii::$app->response->statusCode = 304;
            return Yii::$app->response;
        }

        $r = Yii::$app->response;
        $r->headers->set('ETag', $etag);
        $r->headers->set('Last-Modified', $lastMod);
        $r->headers->set('Cache-Control', 'public, max-age=31536000, immutable'); // из-за ?v=
        return $r->sendFile($abs, null, ['inline' => true]);
    }
}
