<?php

namespace common\components\menu;

use Yii;
use yii\helpers\ArrayHelper;

abstract class BaseNavMenu
{
    /**
     * @return array
     */
    public function getItems()
    {
        $array = $this->_getItemsArray();

        $items = [];
        foreach ($array as $item) {
            if (isset($item['visible']) && !$item['visible']) {
                continue;
            }

            $action = $item['url'];

            $items[] = [
                'label'           => $item['label'],
                'labelCounter'    => ArrayHelper::getValue($item, 'labelCounter'),
                'labelDanger'     => !empty($item['labelDanger']) ? $item['labelDanger'] : false,
                'url'             => $this->_prepareUrl($action),
                'icon'            => ArrayHelper::getValue($item, 'icon'),
                'iconImg'         => ArrayHelper::getValue($item, 'iconImg'),
                'active'          => isset($item['active']) ? $item['active'] : $this->_checkActive($action),
                'isDanger'        => !empty($item['isDanger']) ? $item['isDanger'] : null,
                'items'           => !empty($item['items']) ? $item['items'] : null,
                'isSplit'         => !empty($item['isSplit']) ? $item['isSplit'] : [],
                'submenuTemplate' => !empty($item['submenuTemplate']) ? $item['submenuTemplate'] : null,
                'options'         => !empty($item['options']) ? $item['options'] : [],
            ];
        }

        return $items;
    }

    /**
     * @return array
     */
    abstract protected function _getItemsArray();

    /**
     * @param string $url
     *
     * @return array
     */
    protected function _prepareUrl($url)
    {
        return [$url];
    }

    /**
     * @param string $urlStr
     *
     * @return bool
     */
    protected function _checkActive($urlStr)
    {
        return (bool)strstr(Yii::$app->request->url, $urlStr);
    }
}