<?php

namespace api\controllers\v1;

use common\models\box\Drop;
use OpenApi\Annotations as OA;

/**
 * Служебные эндпоинты по дропам (синхронизация между инсталляциями).
 *
 * @package api\controllers\v1
 * @OA\Tag(name="Drops")
 */
class DropsController extends BaseApiController
{
    /**
     * Список маркет-дропов для консоли drop-parser/new-items (как бывший GET prostoj.store/api/items).
     *
     * @OA\Get(
     *     path="/v1/drops/items",
     *     operationId="getDropsItemsForSync",
     *     tags={"Drops"},
     *     summary="Список дропов для репликации (name, eng_name, image URL, rust_id, …)",
     *     @OA\Response(response=200, description="success + data: массив объектов")
     * )
     */
    public function actionItems()
    {
        /** @var Drop[] $list */
        $list = Drop::find()
            ->cache(60)
            ->andWhere(['<>', 'eng_name', ''])
            ->all();

        $items = [];
        foreach ($list as $item) {
            $categoryName = null;
            if (!empty($item->category)) {
                $categoryName = $item->category->name;
            }
            $items[] = [
                'name' => $item->name,
                'description' => $item->description,
                'eng_name' => $item->eng_name,
                'image' => $item->image(),
                'rust_id' => $item->rust_id,
                'type_id' => $item->type_id,
                'category_id' => $item->category_id,
                'category_name' => $categoryName,
                'blocked_hour' => $item->blocked_hour,
            ];
        }

        return $this->successResponse($items);
    }
}
