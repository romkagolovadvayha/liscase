<?php

namespace common\components\widgets;

use Yii;

class Editable extends \yii2mod\editable\Editable
{
    public $type = 'select';

    public $mode = 'pop';

    /**
     * Initializes the widget.
     */
    public function init()
    {
        parent::init();

        $this->pluginOptions['emptytext'] = Yii::t('common', '-не указано-');
    }
}