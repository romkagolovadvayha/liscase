<?php

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

use yii\helpers\Html;

use common\models\servers\Servers;

$this->title = $name;

\frontend\assets\LastDropAsset::register($this);

?>

<h1><?= Html::encode($this->title) ?></h1>
<?= nl2br(Html::encode($message)) ?>