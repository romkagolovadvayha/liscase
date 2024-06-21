<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\box\Drop;
use common\models\box\Select;
use common\models\box\Sets;
use common\models\invoice\Invoice;
use common\models\promotion\Promotion;
use common\models\user\UserDrop;
use frontend\forms\market\BuyForm;
use frontend\forms\market\BuySelectForm;
use frontend\models\box\DropSearch;
use yii\base\BaseObject;
use yii\bootstrap5\LinkPager;
use yii\helpers\ArrayHelper;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Yii;

class MarketController extends WebController
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

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        $searchModel = new DropSearch();
//        print_r(Yii::$app->request->queryParams);exit;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionFormModal($id)
    {
        $this->layout = 'service';
        $drop = Drop::findOne($id);
        if (empty($drop) || $drop->status !== Drop::STATUS_ACTIVE) {
            throw new NotFoundHttpException(Yii::t('common', 'Предмет не найден!'));
        }
        if (!empty($_POST['buy'])) {
            $user = Yii::$app->user->identity;
            $balance = $user->getPersonalBalance();
            if ($drop->getRealPrice() > $balance->balanceCeil) {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Недостаточно средств на счете!'));
            } else {
                $dbTransaction = Yii::$app->db->beginTransaction();
                try {
                    $comment = Yii::t('common', 'Покупка предмета "{PARAMS_PREDNAME}"', [
                        'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
                    ]);
                    Invoice::createRecord($user->id, $drop->getRealPrice(), Invoice::TYPE_PAYMENT_MARKET_DROP, null, null, $drop->id, $comment);
                    UserDrop::createRecord($user->id, $drop->id, null, null,UserDrop::STATUS_ACTIVE, false, $drop->count);
                    $dbTransaction->commit();
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Предмет успешно приобретен!'));
                } catch (\Exception $e) {
                    $dbTransaction->rollBack();
                    Yii::$app->session->addFlash('danger', Yii::t('common', $e->getMessage()));
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Произошла ошибка при оплате!'));
                }
            }
        }

        return $this->renderAjax('form-modal', [
            'drop' => $drop
        ]);
    }

    public function actionFormModalSet($id)
    {
        $this->layout = 'service';
        /** @var Sets $sets */
        $sets = Sets::findOne($id);
        if (empty($sets)) {
            throw new NotFoundHttpException(Yii::t('common', 'Предмет не найден!'));
        }
        if (!empty($_POST['buy'])) {
            $user = Yii::$app->user->identity;
            $balance = $user->getPersonalBalance();
            if ($sets->getRealPrice() > $balance->balanceCeil) {
                Yii::$app->session->addFlash('danger', Yii::t('common', 'Недостаточно средств на счете!'));
            } else {
                $dbTransaction = Yii::$app->db->beginTransaction();
                try {
                    $comment = Yii::t('common', 'Покупка набора "{PARAMS_PREDNAME}"', [
                        'PARAMS_PREDNAME' => Yii::t('database', $sets->name)
                    ]);
                    Invoice::createRecord($user->id, $sets->getRealPrice(), Invoice::TYPE_PAYMENT_MARKET_DROP, null, $sets->id, null, $comment);
                    foreach ($sets->setsDrop as $setDrop) {
                        UserDrop::createRecord($user->id, $setDrop->drop->id, null, $sets->id, UserDrop::STATUS_ACTIVE, false, $setDrop->count);
                    }
                    $dbTransaction->commit();
                    Yii::$app->session->addFlash('success', Yii::t('common', 'Предмет успешно приобретен!'));
                } catch (\Exception $e) {
                    $dbTransaction->rollBack();
                    print_r($e->getMessage());
                    Yii::$app->session->addFlash('danger', Yii::t('common', 'Произошла ошибка при оплате!'));
                }
            }
        }

        return $this->renderAjax('form-modal-sets', [
            'sets' => $sets
        ]);
    }

    public function actionFormModalSelect($id)
    {
        $this->layout = 'service';
        /** @var BuySelectForm $modelForm */
        $modelForm = BuySelectForm::findOne($id);
        if (empty($modelForm)) {
            throw new NotFoundHttpException(Yii::t('common', 'Товар не найден!'));
        }
        if ($modelForm->load(Yii::$app->request->post())) {
            if ($modelForm->validate()) {
                if ($_POST['buy'] == 1) {
                    $user = Yii::$app->user->identity;
                    $balance = $user->getPersonalBalance();
                    if ($modelForm->drop->getRealPrice() > $balance->balanceCeil) {
                        Yii::$app->session->addFlash('danger', Yii::t('common', 'Недостаточно средств на счете!'));
                    } else {
                        $dbTransaction = Yii::$app->db->beginTransaction();
                        try {
                            $comment = Yii::t('common', 'Покупка предмета "{PARAMS_PREDNAME}"', [
                                'PARAMS_PREDNAME' => Yii::t('database', $modelForm->drop->name)
                            ]);
                            Invoice::createRecord($user->id, $modelForm->drop->getRealPrice(), Invoice::TYPE_PAYMENT_MARKET_DROP, null, null, $modelForm->drop->id, $comment);
                            UserDrop::createRecord($user->id, $modelForm->drop->id, null, null,UserDrop::STATUS_ACTIVE, false, $modelForm->drop->count);
                            $dbTransaction->commit();
                            Yii::$app->session->addFlash('success', Yii::t('common', 'Предмет успешно приобретен!'));
                        } catch (\Exception $e) {
                            $dbTransaction->rollBack();
                            Yii::$app->session->addFlash('danger', Yii::t('common', $e->getMessage()));
                            Yii::$app->session->addFlash('danger', Yii::t('common', 'Произошла ошибка при оплате!'));
                        }
                    }
                }
            } else {
                Yii::$app->session->addFlash('danger', array_values($modelForm->getFirstErrors())[0]);
            }
        }

        return $this->renderAjax('form-modal-select', [
            'model' => $modelForm
        ]);
    }

    /**
     * @param $id
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
//    public function actionView($id)
//    {
//        $drop = Drop::findOne($id);
//        if (empty($drop)) {
//            throw new NotFoundHttpException(Yii::t('common', 'Предмет не найден!'));
//        }
//        if (!empty($_POST['buy'])) {
//            $user = Yii::$app->user->identity;
//            $balance = $user->getPersonalBalance();
//            if ($drop->getPriceMarket() > $balance->balanceCeil) {
//                throw new HttpException(402, Yii::t('common', 'Недостаточно средств на счете!'));
//            }
//            Invoice::createRecord($user->id, $drop->getPriceMarket(), Invoice::TYPE_PAYMENT_MARKET_DROP);
//            UserDrop::createRecord($user->id, $drop->id, null, UserDrop::STATUS_ACTIVE, false);
//            Yii::$app->session->addFlash('success', 'Предмет успешно приобретен!');
//        }
//        return $this->render('view', [
//            'drop' => $drop
//        ]);
//    }
}
