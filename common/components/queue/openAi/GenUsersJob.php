<?php

namespace common\components\queue\openAi;

use common\components\helpers\Role;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;
use common\models\user\UserProfile;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class GenUsersJob extends BaseObject implements JobInterface
{

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        $users = Yii::$app->openAi->getUsers();
//        $users = json_decode('[{"nickname":"alexsmith","name":"\u0410\u043b\u0435\u043a\u0441\u0430\u043d\u0434\u0440","surname":"\u0421\u043c\u0438\u0442","birthdate":"1988-09-12","gender":"male"},{"nickname":"olgalukina","name":"\u041e\u043b\u044c\u0433\u0430","surname":"\u041b\u0443\u043a\u0438\u043d\u0430","birthdate":"1990-05-25","gender":"female"},{"nickname":"petrpetrov","name":"\u041f\u0435\u0442\u0440","surname":"\u041f\u0435\u0442\u0440\u043e\u0432","birthdate":"2000-10-03","gender":"male"},{"nickname":"ekaterinaveranova","name":"\u0415\u043a\u0430\u0442\u0435\u0440\u0438\u043d\u0430","surname":"\u0412\u0435\u0440\u0430\u043d\u043e\u0432\u0430","birthdate":"1996-07-18","gender":"female"},{"nickname":"maximkuznetsov","name":"\u041c\u0430\u043a\u0441\u0438\u043c","surname":"\u041a\u0443\u0437\u043d\u0435\u0446\u043e\u0432","birthdate":"1992-12-30","gender":"male"}]', 1);
        Yii::error("GenUsersJobErrorUsers: " . json_encode($users), 'error');
        foreach ($users as $item) {
           try {
               $model = new User();
               $model->status              = User::STATUS_ACTIVE;
               $exist = User::find()
                            ->andWhere(['username' => $item['nickname']])
                            ->exists();
               if ($exist) {
                   $item['nickname'] = $item['nickname'] . rand(1, 99);
               }
               $model->username              = $item['nickname'];
               $model->email            = $item['nickname'] . "@test" . rand(1, 999) . ".com";
               $model->current_language = Yii::$app->language;
               $model->setPassword(Yii::$app->security->generateRandomString());
               $model->generateAuthKey();
               $model->generateRefCode();
               $model->generateSocketRoom();
               $model->save(false);

               if (!UserProfile::createModel($model)) {
                   throw new \Exception('Error save user profile');
               }

               $userProfileModel = UserProfile::findOne(['user_id' => $model->id]);
               $userProfileModel->name = $item['name'];
               $userProfileModel->surname = $item['surname'];
               $userProfileModel->full_name = $item['name'] . " " . $item['surname'];
               $userProfileModel->gender = $item['gender'] === 'male' ? 1 : 2;
               $userProfileModel->birthday = $item['birthdate'];
               $userProfileModel->save(false);
           } catch (\Exception $e) {
               Yii::error("GenUsersJobErrorItem: " . json_encode($item), 'error');
               Yii::error("GenUsersJobError: " . $e->getMessage(), 'error');
           }
        }
    }

}