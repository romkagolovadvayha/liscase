<?php

namespace frontend\widgets;

use Yii;
use yii\helpers\ArrayHelper;

class Breadcrumbs extends \yii\bootstrap5\Breadcrumbs
{

    /**
     * {@inheritDoc}
     */
    public $tag = 'ol';
    /**
     * @var array|false the first hyperlink in the breadcrumbs (called home link).
     * Please refer to [[links]] on the format of the link.
     * If this property is not set, it will default to a link pointing to [[\yii\web\Application::homeUrl]]
     * with the label 'Home'. If this property is false, the home link will not be rendered.
     */
    public $homeLink = [];
    /**
     * {@inheritDoc}
     */
    public $itemTemplate = "<li class=\"breadcrumb-item\">{link}</li>\n";
    /**
     * {@inheritDoc}
     */
    public $activeItemTemplate = "<li class=\"breadcrumb-item active\" aria-current=\"page\">{link}</li>\n";
    /**
     * @var array the HTML attributes for the widgets nav container tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $navOptions = ['aria' => ['label' => 'breadcrumb']];

    /**
     * {@inheritDoc}
     */
    public function run(): string
    {
        if (empty($this->links)) {
            return '';
        }

        // Normalize links
        $links = [];
        foreach ($this->links as $key => $value) {
            if (is_array($value)) {
                $links[] = $value;
            } else {
                $links[] = ['label' => $value, 'url' => is_string($key) ? $key : null];
            }
        }
        $this->links = $links;
        unset($links);

        if ($this->homeLink === []) {
            $this->homeLink = null;
        }

        if (!isset($this->options['id'])) {
            $this->options['id'] = "{$this->getId()}-breadcrumb";
        }
        Html::addCssClass($this->options, ['widget' => 'breadcrumb']);

        // parent method not return result
        ob_start();
        parent::run();
        $content = ob_get_clean();

        return Html::tag('nav', $content, $this->navOptions);
    }

    /**
     * The template used to render each active item in the breadcrumbs. The token `{link}` will be replaced with the
     * actual HTML link for each active item.
     *
     * @param string $value
     *
     * @return $this
     */
    public function activeItemTemplate(string $value): self
    {
        $this->activeItemTemplate = $value;

        return $this;
    }

    /**
     * Whether to HTML-encode the link labels.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function encodeLabels(bool $value): self
    {
        $this->encodeLabels = $value;

        return $this;
    }

    /**
     * The first hyperlink in the breadcrumbs (called home link).
     *
     * Please refer to {@see links} on the format of the link.
     *
     * If this property is not set, it will default to a link pointing with the label 'Home'. If this property is false,
     * the home link will not be rendered.
     *
     * @param array|false $value
     *
     * @return $this
     */
    public function homeLink($value): self
    {
        $this->homeLink = $value;

        return $this;
    }

    /**
     * The template used to render each inactive item in the breadcrumbs. The token `{link}` will be replaced with the
     * actual HTML link for each inactive item.
     *
     * @param string $value
     *
     * @return $this
     */
    public function itemTemplate(string $value): self
    {
        $this->itemTemplate = $value;

        return $this;
    }

    /**
     * List of links to appear in the breadcrumbs. If this property is empty, the widget will not render anything.
     * Each array element represents a single item in the breadcrumbs with the following structure.
     *
     * @param array $value
     *
     * @return $this
     */
    public function links(array $value): self
    {
        $this->links = $value;

        return $this;
    }

    /**
     * The HTML attributes for the widgets nav container tag.
     *
     * {@see \yii\helpers\Html::renderTagAttributes()} for details on how attributes are being rendered.
     *
     * @param array $value
     *
     * @return $this
     */
    public function navOptions(array $value): self
    {
        $this->navOptions = $value;

        return $this;
    }

    /**
     * The HTML attributes for the widget container tag. The following special options are recognized.
     *
     * {@see \yii\helpers\Html::renderTagAttributes()} for details on how attributes are being rendered.
     *
     * @param array $value
     *
     * @return $this
     */
    public function options(array $value): self
    {
        $this->options = $value;

        return $this;
    }

    /**
     * The name of the breadcrumb container tag.
     *
     * @param string $value
     *
     * @return $this
     */
    public function tag(string $value): self
    {
        $this->tag = $value;

        return $this;
    }
}
