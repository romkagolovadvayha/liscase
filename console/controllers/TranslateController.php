<?php

namespace console\controllers;

use common\components\google\TranslateApi;
use common\models\blog\Blog;
use common\models\box\Box;
use common\models\user\User;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use DemonDogSL\translateManager\models\LanguageTranslate;
use yii\base\BaseObject;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use Yii;

class TranslateController extends Controller
{
    /**
     * translate/import-api
     *
     * @throws \yii\base\InvalidRouteException
     * @throws \yii\console\Exception
     * @throws \yii\db\Exception
     */
    public function actionImportApi()
    {
        Yii::$app->runAction('/translateManager/console/scan');
        Yii::$app->runAction('/translateManager/console/optimize');

        $languages = [
            'en' => 'en-US',
//            'de' => 'de-DE',
//            'uk' => 'uk-UA',
//            'es' => 'es-ES',
        ];

        $google = new TranslateApi();

        foreach ($languages as $targetLanguage => $languageSource) {
            $languageSources = Yii::$app->db->createCommand('
                SELECT id, message 
                FROM `language_source` 
                WHERE id NOT IN (SELECT id FROM language_translate WHERE language = "' . $languageSource . '")
            ')->queryAll();

            if (empty($languageSources)) {
                continue;
            }

            $texts = ArrayHelper::map($languageSources, 'id', 'message');

            foreach ($texts as $id => $text) {
                echo $targetLanguage . ' - ' . $text . PHP_EOL;
                $translateText = $google->translateText($text, $targetLanguage);
                echo $translateText . PHP_EOL;

                $translateText = str_replace('&#39;', "'", $translateText);
                $translateText = str_replace('&quot;', '"', $translateText);

                $model = LanguageTranslate::findOne(['id' => $id, 'language' => $languageSource]);
                if (empty($model)) {
                    $model = new LanguageTranslate();
                    $model->id       = $id;
                    $model->language = $languageSource;
                }

                $model->translation = $translateText;
                $model->save();
            }

            sleep(3);
        }

        $this->actionClearTranslateCache();
    }

    /**
     * translate/clear-translate-cache
     *
     * @throws \yii\base\InvalidRouteException
     * @throws \yii\console\Exception
     * @throws \yii\db\Exception
     */
    public function actionClearTranslateCache()
    {
        $languages = [
            'ru-RU',
            'en-US',
//            'de-DE',
//            'it-IT',
//            'es-ES',
//            'fr-FR',
//            'vi-VN',
//            'id-ID',
//            'hi-IN',
//            'pt-PT',
//            'tr-TR',
//            'hr-HR'
        ];

        foreach ($languages as $language) {
            $key = ['yii\\i18n\\DbMessageSource', 'common', $language];
            Yii::$app->cache->delete($key);
            $key = ['yii\\i18n\\DbMessageSource', 'database', $language];
            Yii::$app->cache->delete($key);
        }
    }

}
