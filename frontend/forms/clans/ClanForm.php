<?php

namespace frontend\forms\clans;

use common\models\clan\Clan;
use common\models\clan\ClanStats;
use common\models\clan\UserClan;
use common\models\serverskin\ServerSkin;
use Imagine\Image\Point;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\validators\ImageValidator;
use yii\web\UploadedFile;

class ClanForm extends Clan
{
    /**
     * @return bool
     */
    public function saveRecord()
    {
        $user = Yii::$app->user->identity;

        $userClans = UserClan::find()
            ->andWhere(['user_id' => $user->identity->id])
            ->andWhere(['status' => 1])
            ->exists();

        if ($userClans) {
            $this->addError('title', Yii::t('common', 'Вы уже состоите в клане'));
            return false;
        }
        if (empty($this->title)) {
            $this->addError('title', Yii::t('common', 'Название клана не должно быть пустым'));
            return false;
        }
        if (empty($this->description_short)) {
            $this->addError('description_short', Yii::t('common', 'Введите краткое описание клана.'));
            return false;
        }

        if (!$this->validate()) {
            return false;
        }

        $this->user_count = 1;
        $this->user_id = $user->id;
        $this->link_hash = md5(uniqid(mt_rand(), true));
        $this->created_at = date('Y-m-d H:i:s');

        if ($this->save()) {
            $userClan = new UserClan();
            $userClan->user_id = $user->id;
            $userClan->steam_id = $user->steam_id;
            $userClan->clan_id = $this->id;
            $userClan->status = 1;
            $userClan->created_at = date('Y-m-d H:i:s');
            $userClan->save();
        }

        return true;
    }
}
