<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

declare(strict_types=1);

namespace common\components\widgets;

use Exception;
use yii\base\InvalidConfigException;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\bootstrap5\Html;
use yii\bootstrap5\Widget;
use yii\helpers\ArrayHelper;

/**
 * Dropdown renders a Bootstrap dropdown menu component.
 *
 * For example,
 *
 * ```php
 * <div class="dropdown">
 *     <a href="#" data-toggle="dropdown" class="dropdown-toggle">Label <b class="caret"></b></a>
 *     <?php
 *         echo Dropdown::widget([
 *             'items' => [
 *                 ['label' => 'DropdownA', 'url' => '/'],
 *                 ['label' => 'DropdownB', 'url' => '#'],
 *             ],
 *         ]);
 *     ?>
 * </div>
 * ```
 * @see https://getbootstrap.com/docs/5.1/components/dropdowns/
 * @author Antonio Ramirez <amigo.cobos@gmail.com>
 * @author Simon Karlen <simi.albi@outlook.com>
 */
class Dropdown extends Widget
{
    /**
     * @var array list of menu items in the dropdown. Each array element can be either an HTML string,
     * or an array representing a single menu with the following structure:
     *
     * - label: string, required, the label of the item link.
     * - encode: bool, optional, whether to HTML-encode item label.
     * - url: string|array, optional, the URL of the item link. This will be processed by [[\yii\helpers\Url::to()]].
     *   If not set, the item will be treated as a menu header when the item has no sub-menu.
     * - visible: bool, optional, whether this menu item is visible. Defaults to true.
     * - disabled: bool, optional, whether this menu item is disabled. Defaults to false.
     * - linkOptions: array, optional, the HTML attributes of the item link.
     * - options: array, optional, the HTML attributes of the item.
     * - active: bool, optional, whether the item should be on active state or not.
     * - items: array, optional, the submenu items. The structure is the same as this property.
     *   Note that Bootstrap doesn't support dropdown submenu. You have to add your own CSS styles to support it.
     * - submenuOptions: array, optional, the HTML attributes for sub-menu container tag. If specified it will be
     *   merged with [[submenuOptions]].
     *
     * To insert divider use `-`.
     */
    public $items = [];
    /**
     * @var bool whether the labels for header items should be HTML-encoded.
     */
    public $encodeLabels = true;
    /**
     * @var array|null the HTML attributes for sub-menu container tags.
     */
    public $submenuOptions = [];

    public $menuHidden = false;

    /**
     * {@inheritDoc}
     */
    public function init(): void
    {
        parent::init();
        Html::addCssClass($this->options, ['widget' => 'dropdown-menu']);
    }

    /**
     * Renders the widget.
     * @return string
     * @throws InvalidConfigException
     */
    public function run(): string
    {
        BootstrapPluginAsset::register($this->getView());
        $this->registerClientEvents();

        return $this->renderItems($this->items, $this->options);
    }

    /**
     * Renders menu items.
     * @param array $items the menu items to be rendered
     * @param array $options the container HTML attributes
     * @return string the rendering result.
     * @throws InvalidConfigException if the label option is not specified in one of the items.
     * @throws Exception
     */
    protected function renderItems(array $items, array $options = []): string
    {
        $lines = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $lines[] = ($item === '-')
                    ? Html::tag('hr', '', ['class' => 'dropdown-divider'])
                    : $item;
                continue;
            }
            if (isset($item['visible']) && !$item['visible']) {
                continue;
            }
            if (!array_key_exists('label', $item)) {
                throw new InvalidConfigException("The 'label' option is required.");
            }
            $encodeLabel = $item['encode'] ?? $this->encodeLabels;
            $label = $encodeLabel ? Html::encode($item['label']) : $item['label'];
            $itemOptions = ArrayHelper::getValue($item, 'options', []);
            $linkOptions = ArrayHelper::getValue($item, 'linkOptions', []);
            $active = ArrayHelper::getValue($item, 'active', false);
            $disabled = ArrayHelper::getValue($item, 'disabled', false);
            $iconClass = ArrayHelper::getValue($item, 'iconClass', null);
            $spanClass = ArrayHelper::getValue($item, 'spanClass', null);
            $notification = ArrayHelper::getValue($item, 'notification', null);
            $visibility = ArrayHelper::getValue($item, 'visibility', null);

            if (isset($item['visibility']) && (empty($visibility) || !$visibility)) {
                return '';
            }

            if ($disabled) {
                ArrayHelper::setValue($linkOptions, 'tabindex', '-1');
                ArrayHelper::setValue($linkOptions, 'aria.disabled', 'true');
                Html::addCssClass($linkOptions, ['disable' => 'disabled']);
            } elseif ($active) {
                ArrayHelper::setValue($linkOptions, 'aria.current', 'true');
                Html::addCssClass($linkOptions, ['activate' => 'active']);
            }

            $icon = '';
            if (!empty($iconClass)) {
                $icon = Html::tag('span', '', ['class' => $iconClass]);
            }
            $notificationText = '';
            if (!empty($notification)) {
                $hiddenClass = '';
                if ($this->menuHidden) {
                    $hiddenClass = 'mini-notification';
                }
                $notificationText = Html::tag('span', $notification, ['class' => 'main-menu-notification ' . $hiddenClass]);
            }
            if (!empty($spanClass)) {
                $spanStyle = '';
                if ($this->menuHidden) {
                    $spanStyle = 'display: none';
                }
                $label = Html::tag('span', $label, ['class' => $spanClass, 'style' => $spanStyle]);
            }

            $url = array_key_exists('url', $item) ? $item['url'] : null;
            if (empty($item['items'])) {
                if ($url === null) {
                    $content = Html::tag('h6', $label, ['class' => 'dropdown-header']);
                } else {
                    $content = Html::a($icon . $label . $notificationText, $url);
                }
                $lines[] = Html::tag('li', $content, $linkOptions);
            } else {
                $submenuOptions = $this->submenuOptions;
                if (isset($item['submenuOptions'])) {
                    $submenuOptions = array_merge($submenuOptions, $item['submenuOptions']);
                }
                Html::addCssClass($submenuOptions, ['widget' => 'dropdown-submenu dropdown-menu']);
                Html::addCssClass($linkOptions, ['toggle' => 'dropdown-toggle']);

                $lines[] = Html::beginTag('div', array_merge_recursive(['class' => ['dropdown'], 'aria' => ['expanded' => 'false']], $itemOptions));
                $lines[] = Html::a($icon . $label . $notificationText, $url, array_merge_recursive([
                    'data' => ['bs-toggle' => 'dropdown'],
                    'aria' => ['expanded' => 'false'],
                    'role' => 'button',
                ], $linkOptions));
                $lines[] = static::widget([
                    'items' => $item['items'],
                    'options' => $submenuOptions,
                    'submenuOptions' => $submenuOptions,
                    'encodeLabels' => $this->encodeLabels,
                ]);
                $lines[] = Html::endTag('div');
            }
        }

        return Html::tag('ul', implode("\n", $lines), $options);
    }
}
