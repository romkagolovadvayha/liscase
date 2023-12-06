<?php

namespace common\models\news;

use Yii;
use yii\data\ActiveDataProvider;

class PublicNewsSearch extends NewsSearch
{
    /**
     * @param array    $params
     * @param callable $filter
     *
     * @return ActiveDataProvider
     */
    public function search($params = [], callable $filter = null)
    {
        return parent::search($params, function ($query) {
            $query->andWhere(['status' => self::STATUS_ACTIVE]);
            $query->orderBy(['date_published' => SORT_DESC, 'id' => SORT_DESC]);
        });
    }

    /**
     * @param string|null $lang
     *
     * @return NewsContent|null
     */
    public function getNewsContentModel($lang = null)
    {
        if (empty($lang)) {
            $lang = Yii::$app->language;
        }

        $model = parent::getContentModel($lang);
        if (empty($model)) {
            $model = parent::getContentModel('en-US');
            if (empty($model)) {
                $model = parent::getContentModel('ru-RU');
            }
        }

        return $model;
    }

    public function getShareUrl()
    {
        $url = Yii::$app->params['homePage'] . '/news/view?id=' . $this->id;

        if (!Yii::$app->user->isGuest) {
            $url .= '&refCode=' . Yii::$app->user->identity->ref_code;
        }

        return $url;
    }
}
