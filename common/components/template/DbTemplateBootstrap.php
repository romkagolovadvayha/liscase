<?php
namespace common\components\template;

use Yii;
use yii\base\BootstrapInterface;
use common\components\DbTemplateService;

class DbTemplateBootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        $templateId = (int)($app->params['activeTemplateId'] ?? 1);
        /** @var DbTemplateService $svc */
        $svc = $app->get('dbTemplates');
        $svc->applyFor($app->view, $templateId);
    }
}
