<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\battle\Battle;
use common\models\battle\BattleRate;
use common\models\box\Box;
use common\models\invoice\Invoice;
use common\models\promocode\Promocode;
use common\models\user\UserBox;
use common\models\user\UserDrop;
use common\models\user\UserPromocode;
use frontend\forms\battle\AddBattleForm;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Yii;

class BattleController extends WebController
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
        return $this->render('index');
    }

    /**
     * @param $status
     *
     * @return string
     */
    public function actionGetList($status)
    {
        $this->layout = 'service';
        return $this->render('_battle_list', [
            'status' => $status
        ]);
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionArchive()
    {
        return $this->render('archive');
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionCreate()
    {
        $model = new AddBattleForm();
        if ($model->load(Yii::$app->request->post())) {
            try {
                $battleId = $model->saveRecord();
                Yii::$app->session->addFlash('success', Yii::t('common', 'Сражение успешно создано!'));
                return $this->redirect('/battle/game?id=' . $battleId);
            } catch (\Exception $ex) {
                Yii::$app->session->addFlash('error', Yii::t('common', $ex->getMessage()));
            }
        }
        return $this->render('create', [
            'model' => $model
        ]);
    }

    /**
     * @param $id
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionGame($id)
    {
        $battle = Battle::findOne($id);
        if (empty($battle)) {
            throw new NotFoundHttpException(Yii::t('common', 'Игра не найдена!'));
        }
        $post = Yii::$app->request->post();
        if (!empty($post['rate'])) {
            $userDrop = UserDrop::findOne($post['rate']);
            if ($battle->player1_user_id === Yii::$app->user->id) {
                throw new HttpException(Yii::t('common', 'Игра не возможна!'));
            }
            if ($battle->status !== Battle::STATUS_WAIT_PLAYER) {
                throw new HttpException(Yii::t('common', 'Игра уже сыграна!'));
            }
            if (empty($userDrop) || $userDrop->status !== UserDrop::STATUS_ACTIVE) {
                throw new HttpException(402, Yii::t('common', 'Не найдена вещь в инвенторе!'));
            }
            $this->_rate($battle, $userDrop);
        }
        return $this->render('game', [
            'battle' => $battle
        ]);
    }

    /**
     * @param Battle $battle
     * @param UserDrop $userDrop
     */
    private function _rate($battle, $userDrop)
    {
        $dbTransaction = Yii::$app->db->beginTransaction();
        try {
            $battle->player2_user_id = $userDrop->user->id;
            BattleRate::createRecord($battle->player2_user_id, $battle->id, $userDrop->id);
            $rand = rand(1, 2);
            if ($rand === 1) {
                $battle->player_winner_user_id = $battle->player1_user_id;
                $battle->player1Rate->userDrop->status = UserDrop::STATUS_ACTIVE;
                $battle->player1Rate->userDrop->save();
                $userDrop->status = UserDrop::STATUS_TEMP_BLOCKED;
                $userDrop->save(false);
                UserDrop::createRecord($battle->player_winner_user_id, $userDrop->drop[0]->id);
            } else {
                $battle->player_winner_user_id = $battle->player2_user_id;
                $userDrop->status = UserDrop::STATUS_ACTIVE;
                $userDrop->save(false);
                UserDrop::createRecord($battle->player_winner_user_id, $battle->player1Rate->userDrop->drop[0]->id);
            }
            $battle->status = Battle::STATUS_FINISH;
            $battle->save(false);
            $dbTransaction->commit();
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            throw new \Exception(Yii::t('common', $e->getMessage()));
        }
    }

    /**
     * @param $id
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionReject($id)
    {
        $battle = Battle::findOne($id);
        if (empty($battle)) {
            throw new NotFoundHttpException(Yii::t('common', 'Игра не найдена!'));
        }
        if ($battle->status !== Battle::STATUS_WAIT_PLAYER) {
            throw new NotFoundHttpException(Yii::t('common', 'Игру нельзя отменить!'));
        }
        if ($battle->player1_user_id !== Yii::$app->user->id) {
            throw new NotFoundHttpException(Yii::t('common', 'Игра не найдена!'));
        }
        $battle->status = Battle::STATUS_REJECT;
        $battle->save();
        $battle->player1Rate->userDrop->status = UserDrop::STATUS_ACTIVE;
        $battle->player1Rate->userDrop->save();
        Yii::$app->session->addFlash('success', Yii::t('common', 'Сражение успешно отменено, предмет возвращен в инвентарь!'));
        return $this->redirect('/battle/index');
    }
}
