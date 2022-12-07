<?php

namespace common\components\oauth;

use common\components\web\Cookie;
use yii\authclient\OpenId;
use yii\base\Action;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\di\Instance;
use yii\helpers\Url;
use yii\web\Response;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\User;

class AuthAction extends \yii\authclient\AuthAction
{

    /**
     * Performs OpenID auth flow.
     * @param OpenId $client auth client instance.
     * @return Response action response.
     * @throws Exception on failure.
     * @throws HttpException on failure.
     */
    protected function authOpenId($client)
    {
        $request = Yii::$app->getRequest();
        $mode = $request->get('openid_mode', $request->post('openid_mode'));

        if (empty($mode)) {
            $url = $client->buildAuthUrl();
            Yii::$app->session->set('referer_link', $_SERVER['HTTP_REFERER']);
            return Yii::$app->getResponse()->redirect($url);
        }

        switch ($mode) {
            case 'id_res':
                if ($client->validate()) {
                    return $this->authSuccess($client);
                }
                throw new HttpException(400, 'Unable to complete the authentication because the required data was not received.');
            case 'cancel':
                return $this->authCancel($client);
            default:
                throw new HttpException(400);
        }
    }


}
