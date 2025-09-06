<?php
namespace common\helpers;

use Yii;
use yii\helpers\Url;
use common\components\template\DbTemplateService;

class DbAsset
{
    public static function js(string $path, ?int $templateId = null): string
    {
        /** @var DbTemplateService $svc */
        $svc = Yii::$app->get('dbTemplates');
        $tid = $templateId ?? (int)(Yii::$app->params['activeTemplateId'] ?? 1);
        $v   = $svc->getVersion($tid);
        return Url::to(['/db-asset/serve', 'root'=>'frontend_sources_js', 'path'=>$path, 'templateId'=>$tid, 'v'=>$v]);
    }
}
