<?php

namespace backend\forms\box;

use common\components\steam\MarketApi;
use common\models\box\DropImage;
use Yii;
use yii\helpers\ArrayHelper;
use common\models\box\Drop;

class DropForm extends Drop
{
    public $market_link;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['market_link'], 'trim'],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'market_link' => 'Ссылка на маркете',
        ]);
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        if ($this->isNewRecord) {
            $this->status = 1;
            $this->created_at = date('Y-m-d H:i:s');
            $this->_parsingMarketIdFromUrl();
            $this->importMarket();
            $exist = Drop::find()
                         ->andWhere(['name' => $this->name])
                         ->exists();
            if ($exist) {
                throw new \Exception('Drop name exist');
            }
        }

        if (!$this->save()) {
            throw new \Exception('Drop not saved');
        }
        $dropId = Yii::$app->db->getLastInsertID();
        $image = "https://cdn.csgo.com/item/" . urlencode($this->eng_name) . "/300.png";
        $this->_loadImage($image, $dropId);

        return true;
    }

    private function _parsingMarketIdFromUrl() {
        preg_match('/https:\/\/market.csgo.com\/item\/(.*?)-(.*?)-/', $this->market_link, $match);
        $marketId = $match[1] . "_" . $match[2];
        if (!empty($marketId)) {
            $this->market_id = $marketId;
        }
    }

    private function _loadImage($imageUrl, $dropId) {
        $uploadDir = Yii::getAlias('@app/web/uploads');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir);
            chmod($uploadDir, 0777);
        }
        $fileUrl = "/drop/" . $dropId . "_" . md5(time()) . ".png";
        $filePath = $uploadDir . $fileUrl;
        file_put_contents($filePath, file_get_contents($imageUrl));
        DropImage::createRecord($fileUrl, DropImage::TYPE_ORIG, $dropId);
    }

}
