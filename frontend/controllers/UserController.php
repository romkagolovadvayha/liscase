<?php

namespace frontend\controllers;

use common\models\box\Box;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use yii\base\BaseObject;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Yii;

class UserController extends Controller
{

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

//    /**
//     * @param $id
//     *
//     * @return \yii\web\Response | string
//     * @throws NotFoundHttpException
//     */
//    public function actionBox($id)
//    {
//        $userBox = UserBox::findOne($id);
//        if (empty($userBox)) {
//            throw new NotFoundHttpException(Yii::t('common', 'Контейнер не найден!'));
//        }
//        return $this->render('box', [
//            'userBox' => $userBox
//        ]);
//    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionInventory()
    {
        $post = Yii::$app->request->post();
        if (!empty($post['sell'])) {
            $userBalance = Yii::$app->user->identity->getPersonalBalance();
            if ($post['sell'] === 'all') {
                $userDrops = UserDrop::find()
                                     ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
                                     ->all();
                if (empty($userDrops)) {
                    throw new HttpException(402, Yii::t('common', 'Не найдены вещи в инвенторе!'));
                }
                /** @var UserDrop[] $userDrops */
                foreach ($userDrops as $userDrop) {
                    $this->_sellUserDrop($userDrop, $userBalance->id);
                }
            } else {
                $userDrop = UserDrop::findOne($post['sell']);
                if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
                    throw new HttpException(402, Yii::t('common', 'Не найдена вещь в инвенторе!'));
                }
                $this->_sellUserDrop($userDrop, $userBalance->id);
            }
        }
        return $this->render('inventory');
    }

    /**
     * @param UserDrop $userDrop
     */
    private function _sellUserDrop($userDrop, $userBalanceId) {
        /** @var Drop $drop */
        foreach ($userDrop->drop as $drop) {
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $drop->price;
            $profit->user_balance_id = $userBalanceId;
            $profit->comment = Yii::t('common', 'Продажа предметов', [], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }
        $userDrop->status = UserDrop::STATUS_SELL;
        $userDrop->save(false);
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionBoxes()
    {
        return $this->render('boxes');
    }

}
