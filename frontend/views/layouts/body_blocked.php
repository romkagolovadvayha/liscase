<?php
/** @var string $content */
/** @var string $lang */
/** @var array $userData */
/** @var Servers[] $servers */

use common\models\servers\Servers;
use common\components\web\Cookie;

$breadcrumbs = null;
$page = isset($this->params['page']) ? $this->params['page'] : '';
if (isset($this->params['breadcrumbs'])) {
    $breadcrumbs = \yii\bootstrap5\Breadcrumbs::widget(['links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],]);
}
$projectStats = \common\models\statistics\Statistics::projectStats();

$lang = substr(Yii::$app->language, 0, 2);
$hiddenMenu = Cookie::getValue('isMenuHide') == 'true';
//$parser = new \ScssPhp\ScssPhp\Compiler();
//$compileFile = $parser->compileFile(__DIR__ . '/styles.scss');
//file_put_contents(Yii::getAlias('@frontend/web/css/styles.min.css'), $compileFile->getCss());
?>

<?php $this->beginBody() ?>

<?=$content?>

<?=Yii::$app->view->render('metrics.twig')?>
<?php $this->endBody() ?>