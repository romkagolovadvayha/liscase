<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace frontend\assets;

use lavrentiev\widgets\toastr\ToastrAsset;
use yii\bootstrap5\BootstrapAsset;
use yii\web\AssetBundle;

/**
 * Main application asset bundle.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class LauncherAsset extends AssetBundle
{
    public $sourcePath = '@frontend/assets/sources';

    public function init()
    {
        parent::init();

        $this->js = [
            'js/launcher.js?v=' . \Yii::$app->settings->get('site_version'),
        ];
        $this->css = [
            'css/launcher.min.css?v=' . \Yii::$app->settings->get('site_version'),
        ];
    }

    public $depends = [
        'yii\web\YiiAsset',
        BootstrapAsset::class,
        ToastrAsset::class,
        MomentAsset::class,
        BalanceAsset::class,
        SocketAsset::class,
    ];
}
