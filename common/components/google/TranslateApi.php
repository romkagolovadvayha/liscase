<?php

namespace common\components\google;

use Yii;

/**
 * Translation via OpenAI (replaces Google Translate for billing-country restrictions).
 * Same public API so all existing callers work unchanged.
 */
class TranslateApi
{
    /**
     * @param array  $texts
     * @param string $targetLanguage
     *
     * @return array list of ['text' => translated string] in same order as $texts
     * @throws \Exception
     */
    public function batchTranslate($texts, $targetLanguage = 'en')
    {
        $result = [];
        foreach ($texts as $text) {
            $result[] = [
                'text' => Yii::$app->openAi->translateText($text, $targetLanguage),
            ];
        }
        return $result;
    }

    /**
     * @param string $text
     * @param string $targetLanguage
     *
     * @return string
     * @throws \Exception
     */
    public function translateText($text, $targetLanguage = 'en')
    {
        return Yii::$app->openAi->translateText($text, $targetLanguage);
    }
}
