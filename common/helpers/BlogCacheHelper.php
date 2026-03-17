<?php

namespace common\helpers;

use Yii;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;

/**
 * Формирование payload категорий блога для API и прогрева кэша.
 */
class BlogCacheHelper
{
    /** TTL кэша категорий в секундах (1 час). */
    public const CATEGORIES_CACHE_TTL = 3600;

    /**
     * Собрать дерево категорий блога (как BlogController::actionCategories).
     *
     * @param string $language
     * @return array
     */
    public static function buildCategoriesPayload(string $language): array
    {
        $prevLang = Yii::$app->language;
        Yii::$app->language = $language;

        try {
            $parentCategories = BlogCategory::find()
                ->where(['blog_category_id' => null])
                ->andWhere(['status' => BlogCategory::STATUS_ACTIVE])
                ->orderBy(['name' => SORT_ASC])
                ->all();

            $categories = [];
            foreach ($parentCategories as $parentCategory) {
                $children = BlogCategory::find()
                    ->where(['blog_category_id' => $parentCategory->id])
                    ->andWhere(['status' => BlogCategory::STATUS_ACTIVE])
                    ->orderBy(['name' => SORT_ASC])
                    ->all();

                $childrenData = [];
                foreach ($children as $child) {
                    $childrenData[] = [
                        'id' => $child->id,
                        'name' => Yii::t('database', $child->name),
                        'linkName' => $child->link_name,
                        'description' => Yii::t('database', $child->description),
                    ];
                }

                $categories[] = [
                    'id' => $parentCategory->id,
                    'name' => Yii::t('database', $parentCategory->name),
                    'linkName' => $parentCategory->link_name,
                    'description' => Yii::t('database', $parentCategory->description),
                    'children' => $childrenData,
                ];
            }
            return $categories;
        } finally {
            Yii::$app->language = $prevLang;
        }
    }
}
