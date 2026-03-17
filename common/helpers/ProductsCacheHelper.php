<?php

namespace common\helpers;

use Yii;
use common\models\box\Category;

/**
 * Формирование payload категорий продуктов для API и прогрева кэша.
 */
class ProductsCacheHelper
{
    /** TTL кэша категорий в секундах (1 час). */
    public const CATEGORIES_CACHE_TTL = 3600;

    /**
     * Собрать список категорий в формате API (как ProductsController::actionCategories).
     *
     * @param int|null $showMainBlock null = все, 0 или 1 — фильтр
     * @param string $language
     * @return array
     */
    public static function buildCategoriesPayload($showMainBlock, string $language): array
    {
        $prevLang = Yii::$app->language;
        Yii::$app->language = $language;

        try {
            $query = Category::find()
                ->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC]);
            if ($showMainBlock !== null) {
                $query->andWhere(['show_main_block' => (int) $showMainBlock]);
            }
            $categories = $query->all();

            $formatted = [];
            foreach ($categories as $category) {
                $categoryImage = null;
                if (!empty($category->image)) {
                    if (strpos($category->image, '/images/') === 0) {
                        $categoryImage = Yii::$app->s3Api->getPublicUrl('uploads' . $category->image);
                    } elseif (strpos($category->image, '/uploads/') === 0) {
                        $categoryImage = Yii::$app->s3Api->getPublicUrl(ltrim($category->image, '/'));
                    } else {
                        $categoryImage = $category->image;
                    }
                }
                $formatted[] = [
                    'id' => $category->id,
                    'name' => Yii::t('database', $category->name),
                    'image' => $categoryImage,
                    'tag' => $category->tag ?? null,
                ];
            }
            return $formatted;
        } finally {
            Yii::$app->language = $prevLang;
        }
    }

    public static function categoriesCacheKey($showMainBlock, string $language): string
    {
        $suffix = $showMainBlock !== null ? (int) $showMainBlock : 'all';
        return 'api_products_categories_' . $suffix . '_' . $language;
    }
}
