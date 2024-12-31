<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;

$dir = realpath(__DIR__ . "/../../../frontend/views/");
$allDir = scandir($dir);
$dirs = [];
foreach ($allDir as $v) {
    if ($v == "." || $v == "..") continue;
    $s = $dir."\\".$v;
    if (is_dir($s)) {
        $dirs[$v] = [];
        $allFiles = scandir($s);
        foreach ($allFiles as $f) {
            if ($f == "." || $f == "..") continue;
            $k = $s."\\".$f;
            if (is_dir($k)) {
                continue;
            }
            if (substr($f, -5) !== '.twig' && substr($f, -5) !== '.scss') {
                continue;
            }
            $dirs[$v][] = $f;
        }
    }
}
?>
<style>
    .title {
        font-weight: 700;
        text-transform: uppercase;
    }
</style>

<?php foreach ($dirs as $dirName => $files): ?>
    <?php if (empty($files)) continue ?>
    <h2><?=$dirName?></h2>
    <?php foreach ($files as $fileName): ?>
        <?=$fileName?><br/>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php //PrintDirectoryTree(realpath(__DIR__ . "/../../../frontend/views/"), 0, 0)?>