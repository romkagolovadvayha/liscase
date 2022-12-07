<?php

namespace common\components\google;

use Yii;
use yii\helpers\ArrayHelper;
use Google\Cloud\Translate\V2\TranslateClient;

class TranslateApi
{
    public function __construct()
    {
        putenv('GOOGLE_APPLICATION_CREDENTIALS='
               . Yii::$app->getBasePath() . '/config/client_translate_credentials.json');
    }

    /**
     * @param array  $texts
     * @param string $targetLanguage
     *
     * @return mixed
     */
    public function batchTranslate($texts, $targetLanguage = 'en')
    {
        $translate = new TranslateClient(['suppressKeyFileNotice' => true]);
        return $translate->translateBatch($texts, [
            'target' => $targetLanguage,
        ]);
    }

    public function translateText($text, $targetLanguage = 'en')
    {
        $translate = new TranslateClient(['suppressKeyFileNotice' => true]);
        $result    = $translate->translate($text, [
            'target' => $targetLanguage,
        ]);

        return ArrayHelper::getValue($result, 'text');
    }
}