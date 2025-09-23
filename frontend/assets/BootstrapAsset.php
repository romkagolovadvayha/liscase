<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace frontend\assets;

use yii\web\AssetBundle;

class BootstrapAsset extends \yii\bootstrap5\BootstrapAsset
{
    public $css = [
        ['css/bootstrap.css', 'rel' => 'preload', 'as' => 'style', 'onload' => "this.onload=null;this.rel='stylesheet'"],
    ];
    public $js = [];
}
