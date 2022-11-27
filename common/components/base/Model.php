<?php

namespace common\components\base;

class Model extends \yii\base\Model
{
    public function unsetAttributes()
    {
        $attributes = $this->attributes();

        foreach ($attributes as $attribute) {
            $this->{$attribute} = null;
        }
    }
}
