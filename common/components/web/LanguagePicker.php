<?php

namespace common\components\web;

use common\assets\LanguagePickerAsset;
use yii\base\Widget;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

class LanguagePicker extends \lajax\languagepicker\widgets\LanguagePicker
{

    public $currentLanguage;
    public $subDomain;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $subDomain = explode('.', $_SERVER['HTTP_HOST'])[0];
        $subDomain = strtolower(str_replace(['https://', 'http://'], '', $subDomain));
        $this->currentLanguage = 'en-US';
        if (in_array($subDomain, array_keys($this->languages))) {
            $this->currentLanguage = $this->languages[$subDomain];
            $this->subDomain = $subDomain;
        }
        $this->languagePluginAsset = LanguagePickerAsset::class;
        parent::init();
    }


    /**
     * Rendering languege element.
     *
     * @param string $language The property of a given language.
     * @param string $name The property of a language name.
     * @param string $template The basic structure of a language element of the displayed language picker
     * Elements to replace: "{link}" URL to call when changing language.
     *  "{name}" name corresponding to a language element, e.g.: English
     *  "{language}" unique identifier of the language element. e.g.: en, en-US
     *
     * @return string the rendered result
     */
    protected function renderItem($language, $name, $template)
    {
        if ($this->encodeLabels) {
            $language = Html::encode($language);
            $name = Html::encode($name);
        }
        $domain = substr(Yii::$app->params['cookieDomain'], 1);
        $protocol = explode($domain, Yii::$app->params['baseUrl'])[0];
        $languageCode = strtolower($this->languages[$language]);
        if ($languageCode !== 'en') {
            $subDomain = $protocol . $languageCode . '.' . $domain;
        } else {
            $subDomain = $protocol . $domain;
        }

        return strtr($template, [
            '{link}' => Url::to($subDomain),
            '{name}' => $name,
            '{language}' => $language,
        ]);
    }
}
