<?php

namespace common\components\widgets;

use Yii;
use yii\helpers\Html;

class Breadcrumbs extends \yii\widgets\Breadcrumbs
{
    public $tag = 'ol';

//    public function run()
//    {
//        $links = [];
//        if (empty($this->links)) {
//            if ($this->homeLink === null) {
//                $links[] = $this->renderItem([
//                    'label' => Yii::t('yii', 'Home'),
//                ], $this->activeItemTemplate);
//
//            } elseif ($this->homeLink !== false) {
//                $homeLink = $this->homeLink;
//
//                if (array_key_exists('url', $homeLink)) {
//                    unset($homeLink['url']);
//                }
//
//                $links[] = $this->renderItem($homeLink, $this->activeItemTemplate);
//            }
//
//        } else {
//            if ($this->homeLink === null) {
//                $links[] = $this->renderItem([
//                    'label' => Yii::t('yii', 'Home'),
//                    'url'   => Yii::$app->homeUrl,
//                ], $this->itemTemplate);
//
//            } elseif ($this->homeLink !== false) {
//                $links[] = $this->renderItem($this->homeLink, $this->itemTemplate);
//            }
//
//            foreach ($this->links as $link) {
//                if (!is_array($link)) {
//                    $link = ['label' => $link];
//                }
//
//                $links[] = $this->renderItem($link,
//                    isset($link['url']) ? $this->itemTemplate : $this->activeItemTemplate);
//            }
//        }
//
//        echo Html::tag($this->tag, implode('', $links), $this->options);
//    }
}
