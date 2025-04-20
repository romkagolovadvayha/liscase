<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\components\google\TranslateApi;
use Yii;
use yii\base\BaseObject;

/**
 * @property int                 $id
 * @property string              $name
 * @property string              $tag
 * @property string              $image
 * @property int                 $sort
 * @property bool                $show_main_block
 * @property string              $created_at
 */
class Category extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'category';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'tag'              => Yii::t('common', 'Тэг'),
            'sort'              => Yii::t('common', 'Сортировка'),
            'show_main_block'              => Yii::t('common', 'Показывать на главной странице'),
            'created_at'              => Yii::t('common', 'Тип'),
        ];
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['sort', 'show_main_block'], 'integer'],
            [['name', 'tag', 'created_at'], 'string', 'max' => 255],
        ];
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($name, $tag)
    {
        $category = Category::find()
                            ->andWhere(['name' => $name])
                            ->andWhere(['tag' => $tag])
                            ->one();

        if (!empty($category)) {
            return $category->id;
        }

        $model = new Category();
        $model->name = $name;
        $model->tag = $tag;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return Yii::$app->db->getLastInsertID();
    }

    public static function getCategoryList() {
        $types = Category::find()->all();
        $list = [];
        foreach ($types as $item) {
            $list[$item->id] = Yii::t('database', $item->name);
        }
        return $list;
    }

    /**
     * @param false $mainBlock
     * @param false $update
     *
     * @return Category[]|false|mixed|\yii\db\ActiveRecord[]
     */
    public static function getCategories($mainBlock = false, $update = false) {
        $cacheKey = 'getCategories_' . $mainBlock;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }
        $result = Category::find()
                          ->andWhere(['show_main_block' => $mainBlock])
                          ->all();
        Yii::$app->cache->set($cacheKey, $result, 7*24*60*60);
        return $result;
    }

}
