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
 * @property int              $sort
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
            'created_at'              => Yii::t('common', 'Тип'),
        ];
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['sort'], 'integer'],
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

}
