<?php

namespace common\components\web;

use Yii;

class Language
{
    public function setDefaultLanguage()
    {
        $languagePicker = Yii::$app->languagepicker;
        if (Cookie::getValue($languagePicker->cookieName)) {
            return;
        }

        $language = $this->_getLanguageByIp();

        Yii::$app->language = $language;

        $languagePicker->saveLanguageIntoCookie($language);

        if (is_callable($languagePicker->callback)) {
            call_user_func($languagePicker->callback);
        }
    }

    /**
     * @return string|null
     */
    private function _getLanguageByIp()
    {
        $language = 'ru-RU';
        if (!GeoIp::getIsRussia()) {
            $countryCode = GeoIp::getCountry();

            $language = 'en-US';
            if ($countryCode == 'VN') {
                $language = 'vi-VN';
            } elseif ($countryCode == 'ID') {
                $language = 'id-ID';
            } elseif ($countryCode == 'IN') {
                $language = 'hi-IN';
            } elseif (in_array($countryCode, ['PT', 'BR'])) {
                $language = 'pt-PT';
            } elseif ($countryCode == 'IT') {
                $language = 'it-IT';
            } elseif ($countryCode == 'DE') {
                $language = 'de-DE';
            } elseif ($countryCode == 'FR') {
                $language = 'fr-FR';
            } elseif ($countryCode == 'ES') {
                $language = 'es-ES';
            } elseif ($countryCode == 'TR') {
                $language = 'tr-TR';
            } elseif (in_array($countryCode, ['HR'])) {
                $language = 'hr-HR';
            }
        }

        return $language;
    }

    /**
     * @return array
     */
    public static function getLanguageList()
    {
        return [
            'ru-RU' => Yii::t('common', 'Русский'),
            'en-US' => Yii::t('common', 'Английский'),
            'de-DE' => Yii::t('common', 'Немецкий'),
            'it-IT' => Yii::t('common', 'Итальянский'),
            'es-ES' => Yii::t('common', 'Испанский'),
            'fr-FR' => Yii::t('common', 'Французский'),
            'vi-VN' => Yii::t('common', 'Вьетнамский'),
            'id-ID' => Yii::t('common', 'Индонезийский'),
            'hi-IN' => Yii::t('common', 'Хинди'),
            'pt-PT' => Yii::t('common', 'Португальский'),
            'tr-TR' => Yii::t('common', 'Турецкий'),
            'hr-HR' => Yii::t('common', 'Хорватский'),
        ];
    }
}