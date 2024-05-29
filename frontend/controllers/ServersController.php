<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\servers\Servers;
use common\models\stats\Wipe;
use yii\web\NotFoundHttpException;
use Yii;

class ServersController extends WebController
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
     * @param $server
     *
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionRules($server)
    {
        /** @var Servers $server */
        $server = Servers::find()
                         ->cache(30)
                         ->andWhere(['tag' => $server])
                         ->one();

        if (empty($server)) {
            throw new NotFoundHttpException(Yii::t('common', 'Сервер не найден!'));
        }

        return $this->render('rules', [
            'server' => $server
        ]);
    }

    public function actionWipeBlock()
    {
        $this->layout = 'service';
        return $this->renderAjax('wipe-block');
    }

}
