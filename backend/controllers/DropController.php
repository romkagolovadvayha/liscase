<?php

namespace backend\controllers;

use backend\forms\box\DropForm;
use common\components\base\Model;
use common\components\helpers\Role;
use common\models\box\Drop;
use common\models\box\DropSearch;
use common\models\user\User;
use yii\helpers\Json;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yii;

use function frontend\controllers\findKey;

class DropController extends \backend\components\CrudController
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
        ];
    }

    protected function _getSearchClassName()
    {
        return DropSearch::class;
    }

    protected function _getFormClassName()
    {
        return DropForm::class;
    }

    /**
     * @param Model $formModel
     * @param string $view
     *
     * @return string|array|\yii\web\Response
     */
    protected function _saveForm($formModel, $view)
    {
        if ($formModel->load(Yii::$app->request->post())) {
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return \yii\bootstrap5\ActiveForm::validate($formModel);
            }
            if ($formModel->saveRecord()) {
                Drop::updateCache();
                // Очищаем кэш API продуктов
                $this->clearProductsCache($formModel->id ?? null);
                return $this->redirect($this->getIndexUrl());
            }
        }
        return $this->render($view, [
            'model' => $formModel,
        ]);
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionDelete($id)
    {
        $formModel = Drop::findOne($id);
        if ($formModel !== null) {
            // Очищаем кэш API продуктов перед удалением
            $this->clearProductsCache($id);
            $formModel->delete();
        }

        $this->_setSearchModel();
        $this->_rememberIndexUrl();
        return $this->_renderIndex($this->_getSearchDataProvider());
    }

    /**
     * Очистка кэша продуктов API
     */
    protected function clearProductsCache($productId = null)
    {
        // Очищаем кэш списка продуктов (все возможные варианты limit)
        for ($limit = 20; $limit <= 100; $limit += 20) {
            Yii::$app->cache->delete('api_products_list_' . $limit);
        }
        // Очищаем кэш конкретного продукта, если указан ID
        if ($productId) {
            Yii::$app->cache->delete('api_products_view_' . $productId);
        }
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionSearch($term)
    {
        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->andFilterWhere(['LIKE', 'name', $term])
            ->all();

        $result = [];
        foreach ($drops as $item) {
            $result[$item->id] = $item->name;
        }
        echo json_encode($result);
    }

    /**
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     */
    public function actionSort()
    {
        if (!empty($_POST)) {
            $sort = 0;
            foreach ($_POST['items'] as $itemId) {
                $drop = Drop::findOne($itemId);
                $drop->sort = $sort;
                $drop->save();
                $sort++;
            }
        }

        /** @var Drop[] $drops */
        $drops = Drop::find()
            ->andWhere(['market_status' => Drop::MARKET_STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        Drop::updateCache();
        
        // Очищаем кэш API продуктов после изменения сортировки
        $this->clearProductsCache();

        return $this->render('sort', [
            'items' => $drops
        ]);
    }

    /**
     * Обновление списка предметов в наборе (для pjax)
     */
    public function actionItemsList($id)
    {
        $model = $this->findModel($id);
        
        return $this->renderPartial('_items_list', [
            'model' => $model,
        ]);
    }

    public function actionSearchDrop($q = null) {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        /** @var Drop[] $drops */
        $dropsQuery = Drop::find()
                     ->andWhere('rust_id is not null');

        if (!empty($q)) {
            $dropsQuery->andWhere(['LIKE', 'name', '%' . $q . '%', false]);
        }

        $drops = $dropsQuery
            ->orderBy(['sort' => SORT_ASC])
            ->limit(20)
            ->all();

        $items = [];
        foreach ($drops as $item) {
            $items[] = [
                'id' => $item->id,
                'name' => $item->name,
                'image' => $item->imageOrig->getImagePubUrl(),
            ];
        }

        return ['items' => $items];
    }

    /**
     * JSON API для Select2 - список предметов
     */
    public function actionListJson($q = null) {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $dropsQuery = Drop::find()
            ->andWhere('rust_id is not null');

        if (!empty($q)) {
            $dropsQuery->andWhere(['LIKE', 'name', $q]);
        }

        $drops = $dropsQuery
            ->orderBy(['sort' => SORT_ASC])
            ->limit(50)
            ->all();

        $results = [];
        foreach ($drops as $drop) {
            $results[] = [
                'id' => $drop->id,
                'text' => Json::encode([
                    'id' => $drop->id,
                    'name' => $drop->name,
                    'image' => $drop->imageOrig ? $drop->imageOrig->getImagePubUrl() : '',
                ]),
            ];
        }

        return ['results' => $results];
    }

    /**
     * JSON API для получения одного предмета
     */
    public function actionGetJson($id) {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $drop = Drop::findOne($id);
        if (!$drop) {
            return ['id' => null, 'text' => ''];
        }

        return [
            'id' => $drop->id,
            'text' => Json::encode([
                'id' => $drop->id,
                'name' => $drop->name,
                'image' => $drop->imageOrig ? $drop->imageOrig->getImagePubUrl() : '',
            ]),
        ];
    }

}