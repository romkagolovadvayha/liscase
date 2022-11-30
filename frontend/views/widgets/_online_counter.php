<?php
use yii\web\View;

/** @var View $this */

$h = date('G');
$randStart = 790;
$randEnd = 840;
if ($h >= 0 && $h <= 1) {
    $randStart = 910;
    $randEnd = 990;
}
if ($h >= 2 && $h <= 2) {
    $randStart = 510;
    $randEnd = 590;
}
if ($h >= 3 && $h <= 6) {
    $randStart = 410;
    $randEnd = 490;
}
if ($h >= 7 && $h <= 9) {
    $randStart = 310;
    $randEnd = 390;
}
if ($h >= 10 && $h <= 12) {
    $randStart = 690;
    $randEnd = 740;
}
if ($h >= 13 && $h <= 15) {
    $randStart = 760;
    $randEnd = 820;
}
if ($h >= 16 && $h <= 17) {
    $randStart = 610;
    $randEnd = 690;
}
if ($h >= 18 && $h <= 20) {
    $randStart = 910;
    $randEnd = 990;
}
?>
<?=rand($randStart, $randEnd)?>