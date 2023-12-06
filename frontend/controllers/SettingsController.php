<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\settings\Settings;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class SettingsController extends WebController
{

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    public function actionColors()
    {
        Yii::$app->layout = null;
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->add('Content-Type', 'text/css');
        return ":root {
            --gen-body-background: " . Settings::getByKey('body_background') . ";
            --gen-body-background-image: url(/images/background.png);
            --gen-a-color: " . Settings::getByKey('a_color') . ";
            --gen-header-top-background: " . Settings::getByKey('header_top_background') . ";
            --gen-header-top-color: " . Settings::getByKey('header_top_color') . ";
            --gen-header-background: " . Settings::getByKey('header_background') . ";
            --gen-header-color: " . Settings::getByKey('header_color') . ";
            --gen-logo-background: " . Settings::getByKey('logo_background') . ";
            --gen-logo-color: " . Settings::getByKey('logo_color') . ";
            --gen-footer: " . Settings::getByKey('footer') . ";
            --gen-footer-text: " . Settings::getByKey('footer_text') . ";
            --gen-button: " . Settings::getByKey('button') . ";
            --gen-button-text: " . Settings::getByKey('button_text') . ";
            --gen-blog-item-data-color: " . Settings::getByKey('blog_item_data_color') . ";
            --gen-blog-item-color: " . Settings::getByKey('blog_item_color') . ";
            --gen-blog-item-background: " . Settings::getByKey('blog_item_background') . ";
        }";
    }

}
