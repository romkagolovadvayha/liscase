<?php

namespace common\helpers;

use Yii;
use common\models\blog\BlogCategory;

/**
 * Формирование payload категорий блога для API и прогрева кэша.
 */
class BlogCacheHelper
{
    /**
     * Языковые суффиксы ключей `api_blog_list_*` и `api_blog_categories_*`
     * (полные теги i18n + короткие из console/storage/warm-caches).
     */
    public const API_BLOG_CACHE_LANGUAGE_KEYS = [
        'en-US', 'ru-RU', 'de-DE', 'uk-UA', 'es-ES',
        'en', 'ru',
    ];

    /** TTL дерева категорий и списка постов API, сек. (как строка drop — 5 мин). */
    public const CATEGORIES_CACHE_TTL = 300;

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

    /**
     * Сброс кэша списка постов `/v1/blog` (все limit × языки).
     */
    public static function invalidateBlogListApiCache(): void
    {
        $cache = Yii::$app->cache;
        foreach (self::API_BLOG_CACHE_LANGUAGE_KEYS as $lang) {
            for ($limit = 10; $limit <= 50; $limit += 10) {
                $cache->delete('api_blog_list_' . $limit . '_' . $lang);
            }
        }
    }

    /**
     * Сброс кэша `/v1/blog/categories` по всем известным языковым ключам.
     */
    public static function invalidateBlogCategoriesApiCache(): void
    {
        $cache = Yii::$app->cache;
        foreach (self::API_BLOG_CACHE_LANGUAGE_KEYS as $lang) {
            $cache->delete('api_blog_categories_' . $lang);
        }
        // Старый ключ без суффикса (до i18n в ключе)
        $cache->delete('api_blog_categories');
    }
}
