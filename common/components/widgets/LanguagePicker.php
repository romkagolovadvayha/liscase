<?php

namespace common\components\widgets;

use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

class LanguagePicker extends \lajax\languagepicker\widgets\LanguagePicker
{
    public $languagePluginAsset = \common\assets\LanguagePickerAsset::class;
    public $parentTemplate      = '<div class="language-picker dropdown-picker-list {size}"><div>{activeItem}<ul>{items}</ul></div></div>';

    protected function renderItem($language, $name, $template)
    {
        if ($this->encodeLabels) {
            $language = Html::encode($language);
            $name = Html::encode($name);
        }

        $baseUrl = [''];
        if ($this->skin == self::SKIN_BUTTON) {
            $baseUrl = ['/auth/login'];
        }

        $params = array_merge($baseUrl, Yii::$app->request->queryParams, ['language-picker-language' => $language]);

        return strtr($template, [
            '{link}' => Url::to($params),
            '{name}' => $name,
            '{language}' => $language,
        ]);
    }
}
