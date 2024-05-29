<?php
namespace backend\widgets;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

class Menu extends \hail812\adminlte\widgets\Menu
{
    /**
     * @inheritdoc
     */
    public $linkTemplate = '<a class="nav-link {active}" href="{url}" {target}>{icon} {label}</a>';

    /**
     * @inheritdoc
     */
    public $labelTemplate = '<p>{label} {treeFlag} {badgeWarning} {badgeDanger} {badgeSuccess}</p>';

    /**
     * @var string treeview wrapper
     */
    public $treeTemplate = "\n<ul class='nav nav-treeview'>\n{items}\n</ul>\n";

    /**
     * @var string
     */
    public static $iconDefault = 'circle';

    /**
     * @var string
     */
    public static $iconStyleDefault = 'fas';

    /**
     * @inheritdoc
     */
    public $itemOptions = ['class' => 'nav-item'];

    /**
     * @inheritdoc
     */
    public $activateParents = true;

    /**
     * @inheritdoc
     */
    public $options = [
        'class' => 'nav nav-pills nav-sidebar flex-column',
        'data-widget' => 'treeview',
        'role' => 'menu',
        'data-accordion' => 'false'
    ];

    protected function renderItems($items)
    {
        $n = count($items);
        $lines = [];
        foreach ($items as $i => $item) {
            $options = array_merge($this->itemOptions, ArrayHelper::getValue($item, 'options', []));

            if (isset($item['items'])) {
                Html::addCssClass($options, 'has-treeview');
            }

            if (isset($item['header']) && $item['header']) {
                Html::removeCssClass($options, 'nav-item');
                Html::addCssClass($options, 'nav-header');
            }

            $tag = ArrayHelper::remove($options, 'tag', 'li');
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
                $treeTemplate = ArrayHelper::getValue($item, 'treeTemplate', $this->treeTemplate);
                $menu .= strtr($treeTemplate, [
                    '{items}' => $this->renderItems($item['items']),
                ]);
                if ($item['active']) {
                    $options['class'] .= ' menu-open';
                }
            }

            if (!isset($item['visibility']) || $item['visibility'] !== false) {
                $lines[] = Html::tag($tag, $menu, $options);
            }
        }

        return implode("\n", $lines);
    }

    protected function renderItem($item)
    {
        if(isset($item['header']) && $item['header']) {
            return $item['label'];
        }

        if (isset($item['iconClass'])) {
            $iconClass = $item['iconClass'];
        } else {
            $iconStyle = $item['iconStyle'] ?? static::$iconStyleDefault;
            $icon = $item['icon'] ?? static::$iconDefault;
            $iconClassArr = ['nav-icon', $iconStyle, 'fa-'.$icon];
            isset($item['iconClassAdded']) && $iconClassArr[] = $item['iconClassAdded'];
            $iconClass = implode(' ', $iconClassArr);
        }
        $iconHtml = '<i class="'.$iconClass.'"></i>';

        $treeFlag = '';
        if (isset($item['items'])) {
            $treeFlag = '<i class="right fas fa-angle-left"></i>';
        }

        if (isset($item['visibility']) && $item['visibility'] === false) {
            return null;
        }

        $template = ArrayHelper::getValue($item, 'template', (isset($item['linkTemplate']))? $item['linkTemplate'] : $this->linkTemplate);
        return strtr($template, [
            '{label}' => strtr($this->labelTemplate, [
                '{label}' => $item['label'],
                '{badgeSuccess}' => !empty($item['badgeSuccess']) ? "<span class=\"badge badge-success\">{$item['badgeSuccess']}</span>" : '',
                '{badgeWarning}' => !empty($item['badgeWarning']) ? "<span class=\"badge badge-warning\">{$item['badgeWarning']}</span>" : '',
                '{badgeDanger}' => !empty($item['badgeDanger']) ? "<span class=\"badge badge-danger\">{$item['badgeDanger']}</span>" : '',
                '{treeFlag}' => $treeFlag
            ]),
            '{url}' => isset($item['url']) ? Url::to($item['url']) : '#',
            '{icon}' => $iconHtml,
            '{active}' => $item['active'] ? $this->activeCssClass : '',
            '{target}' => isset($item['target']) ? 'target="'.$item['target'].'"' : ''
        ]);
    }
}