<?php

namespace common\components\widgets;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;

class AmpleAdminMenu extends \yii\widgets\Menu
{
    public $options         = ['class' => 'nav', 'id' => 'side-menu'];
    public $linkTemplate    = '<a href="{url}" class="waves-effect {isActive} {isDanger} {isImage}">{iconImg} <span class="hide-menu">{label}</span></a>';
    public $submenuTemplate = "\n<ul class='nav {submenuClass}'>\n{items}\n</ul>\n";
    public $activateParents = true;
    public $encodeLabels    = false;

    /**
     * Recursively renders the menu items (without the container tag).
     *
     * @param array $items the menu items to be rendered recursively
     * @param int   $countIteration
     *
     * @return string the rendering result
     */
    protected function renderItems($items, $countIteration = 1)
    {
        $n = count($items);

        $lines = [];
        foreach ($items as $i => $item) {
            $options = array_merge($this->itemOptions, ArrayHelper::getValue($item, 'options', []));
            $isSplit     = ArrayHelper::getValue($item, 'isSplit');
            if ($isSplit) {
                $options = array_merge(['class' => 'split'], $options);
            }
            $tag     = ArrayHelper::remove($options, 'tag', 'li');

            $class = [];
            if ($item['active']) {
                $class[] = $this->activeCssClass;
            }

            if ($i === 0 && $this->firstItemCssClass !== null) {
                $class[] = $this->firstItemCssClass;
            }

            if ($i === $n - 1 && $this->lastItemCssClass !== null) {
                $class[] = $this->lastItemCssClass;
            }

            Html::addCssClass($options, $class);

            $menu = $this->renderItem($item);

            if (!empty($item['items'])) {
                $submenuClass = 'nav-second-level';
                if ($countIteration > 1) {
                    $submenuClass = 'nav-third-level';
                }

                $submenuTemplate = ArrayHelper::getValue($item, 'submenuTemplate', $this->submenuTemplate);

                $menu .= strtr($submenuTemplate, [
                    '{items}'        => $this->renderItems($item['items'], $countIteration + 1),
                    '{submenuClass}' => $submenuClass,
                ]);
            }

            $lines[] = Html::tag($tag, $menu, $options);
        }

        return implode("\n", $lines);
    }

    /**
     * Renders the content of a menu item.
     * Note that the container and the sub-menus are not rendered here.
     *
     * @param array $item the menu item to be rendered. Please refer to [[items]] to see what data might be in the item.
     *
     * @return string the rendering result
     */
    protected function renderItem($item)
    {
        if (empty($item['url'])) {
            $item['url'] = 'javascript:void(0);';
        }

        $label = $item['label'];

        $labelCounter = ArrayHelper::getValue($item, 'labelCounter');
        $isDanger     = ArrayHelper::getValue($item, 'isDanger');
        if (!empty($labelCounter)) {
            $label .= Html::tag('span', $labelCounter, ['class' => 'label label-rounded label-danger pull-right']);
        }

        $items = ArrayHelper::getValue($item, 'items');
        if (!empty($items)) {
            $label .= ' <span class="fa arrow"></span>';
        }

        $isActiveClass = '';
        if ($item['active']) {
            $isActiveClass = $this->activeCssClass;
        }

        $linkTemplate = ArrayHelper::getValue($item, 'template', $this->linkTemplate);

        $icon    = ArrayHelper::getValue($item, 'icon');
        $iconImg = ArrayHelper::getValue($item, 'iconImg');

        $iconLabel = '';
        if (empty($icon)) {
            $iconLabel = ArrayHelper::getValue($item, 'iconLabel');
        }

        $url = Url::toRoute($item['url']);
        if (strstr($url, 'http://')) {
            $url = Url::to($item['url'], 'http');
        } elseif (strstr($url, 'https://')) {
            $url = Url::to($item['url'], 'https');
        }

        $iconTemplate = $iconImg
            ? Html::img($iconImg, ['class' => 'icon-image'])
            : '<i class="' . $icon . ' mdi fa-fw">' . $iconLabel . '</i>';

        return strtr($linkTemplate, [
            '{url}'      => Html::encode($url),
            '{label}'    => $label,
            '{iconImg}'  => $iconTemplate,
            '{isActive}' => $isActiveClass,
            '{isImage}'  => $iconImg ? 'has-image' : '',
            '{isDanger}' => $isDanger ? 'danger' : '',
        ]);
    }
}
