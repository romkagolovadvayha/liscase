<?php

namespace common\models\serverskin;

use common\components\google\TranslateApi;
use common\components\helpers\DateHelper;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "server_skin_category".
 *
 * @property int         $id
 * @property string      $name
 * @property string      $key
 * @property string|null $created_at
 *
 * @property ServerSkin[] $serverSkins
 */
class ServerSkinCategory extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'server_skin_category';
    }

    /**
     * Gets query for [[ServerSkins]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServerSkins()
    {
        return $this->hasMany(ServerSkin::class, ['server_skin_category_id' => 'id']);
    }

    /**
     * @param $key
     *
     * @return ServerSkinCategory|\yii\db\ActiveRecord|null
     */
    public static function getCategory($key) {
        $model = ServerSkinCategory::find()
            ->andWhere(['key' => $key])
            ->one();

        if (empty($model)) {
            $google = new TranslateApi();
            $name = mb_convert_case($google->translateText('box', 'ru'), MB_CASE_TITLE, "UTF-8");

            $model = new ServerSkinCategory();
            $model->name = $name;
            $model->key = $key;
            $model->created_at = date('Y-m-d H:i:s');
            $model->save(false);
        }

        return $model;
    }
}
