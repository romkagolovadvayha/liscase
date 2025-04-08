<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\stats\Teams;
use common\models\stats\Wipe;
use frontend\models\banlist\BansSearch;
use yii\base\BaseObject;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use Yii;

class ReferralController extends WebController
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
        if (!Yii::$app->settings->get('section_referral')) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        $this->view->params['page'] = 'referral';

        $this->view->params['meta_description'] = Yii::t('common', "Зарабатывайте, играя на наших серверах Rust! Узнайте всё о реферальной системе: привлекайте новых игроков, получайте бонусы и уникальные награды. Стримеры и блогеры — получите специальные условия для монетизации вашего контента. Начните зарабатывать уже сегодня, играя в Rust на наших серверах!");

        return $this->render('referral');
    }

}
