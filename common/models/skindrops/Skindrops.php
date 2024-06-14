<?php

namespace common\models\skindrops;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $name
 * @property string              $steam_id
 * @property string              $player
 * @property string              $price
 * @property string              $real_price
 * @property string              $image
 * @property string              $replay
 * @property string              $created_at
 */
class Skindrops extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'skindrops';
    }

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return Yii::$app->db_skindrops;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'steam_id'   => Yii::t('common', 'Steam ID'),
            'player'     => Yii::t('common', 'Имя игрока'),
            'name'       => Yii::t('common', 'Скин'),
            'price'      => Yii::t('common', 'Цена в стиме'),
            'real_price' => Yii::t('common', 'Цена в маркете'),
            'image'      => Yii::t('common', 'Изображение'),
            'replay'     => Yii::t('common', 'Ссылка повторить покупку'),
            'created_at' => Yii::t('common', 'Тип'),
        ];
    }

    public static function getUrlQuery($url, $key = null) {
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (is_null($key)) {
                return $query;
            } elseif (isset($query[$key])) {
                return $query[$key];
            }
        }

        return false;
    }

}
