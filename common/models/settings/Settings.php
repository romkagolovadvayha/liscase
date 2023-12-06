<?php

namespace common\models\settings;

use common\components\web\Cookie;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "settings".
 *
 * @property string    $key
 * @property string    $value
 * @property string    $text
 */
class Settings extends \common\components\base\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'settings';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['key'], 'required'],
            [['key', 'value', 'text'], 'string'],
        ];
    }

    public static function getByKey($key, $translate = true) {
        /** @var Settings $setting */
        $settings = Settings::find()->cache(10)->all();
        $model = null;
        foreach ($settings as $setting) {
            if ($setting->key === $key) {
                $model = $setting;
                break;
            }
        }
        if (empty($model)) {
            return '';
        }
        if (!empty($model->value)) {
            return $model->value;
        }
        if ($translate) {
            return Yii::t('database', $model->text);
        }
        return $model->text;
    }

    public static function setByKey($key, $value = null, $text = null) {
        /** @var Settings $setting */
        $setting = Settings::find()->andWhere(['key' => $key])->one();
        if (empty($setting)) {
            $setting = new Settings();
        }
        $setting->key = $key;
        if (!empty($value)) {
            $setting->value = $value;
        }
        if (!empty($text)) {
            $setting->text = $text;
        }
        $setting->save();
        return true;
    }
}
