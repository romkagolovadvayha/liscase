<?php

namespace common\components\settings;

use common\models\site\SiteSetting;
use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;

class Settings
{
    /**
     * @param $key
     *
     * @return array|string
     */
    public function get($key): string
    {
        $items = $this->getSettings();
        if (empty($items[$key])) {
            return '';
        }
        return $items[$key];
    }
    /**
     * @param $key
     *
     * @return array|string
     */
    public function set($key, $value): string
    {
        $cacheKey = 'Settings_getSettings';
        /** @var SiteSetting[] $settings */
        $settings = SiteSetting::find()
            ->all();

        foreach ($settings as $item) {
            $_key = $item->category . "_" . $item->code;
            if ($_key != $key) {
                continue;
            }

            $item->value = $value;
            $item->save(false);

            Yii::$app->cache->delete($cacheKey);
            return true;
        }
        return false;
    }

    /**
     * @param bool $update
     *
     * @return array|false|mixed
     */
    public function getSettings($update = false) {
        $cacheKey = 'Settings_getSettings';
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }
        $result = [];

        /** @var SiteSetting[] $settings */
        $settings = SiteSetting::find()
            ->all();
        foreach ($settings as $item) {
            $key = $item->category . "_" . $item->code;
            $result[$key] = $item->getValue();
        }

        Yii::$app->cache->set($cacheKey, $result, 3 * 60 * 60);
        return $result;
    }

    public function genColors() {
        /** @var SiteSetting[] $colors */
        $colors = SiteSetting::find()
                    ->andWhere(['IN', 'category', ['colors', 'design', 'clans']])
                    ->all();

        $css = ":root {\n";
        foreach ($colors as $color) {
            $code = str_replace('_', '-', $color->code);
            if ($color->category === 'clans') {
                $code = $color->category . '-' . $color->type . '-' . $code;
            }
            if ($color->type === 'image') {
                $css .= "--{$code}: url({$color->value});\n";
            } else {
                $css .= "--{$code}: {$color->value};\n";
            }
        }
        $css .= "}\n";

        $filePath = Yii::getAlias('@frontend/web/uploads/site/colors/colors.css');
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }

        file_put_contents($filePath, $css);
    }
}
