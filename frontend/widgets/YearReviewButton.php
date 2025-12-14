<?php

namespace frontend\widgets;

use common\components\helpers\Role;
use Yii;
use yii\base\Widget;

/**
 * Виджет кнопки "ИТОГИ ГОДА" в правом нижнем углу
 */
class YearReviewButton extends Widget
{
    /**
     * @var int ID сервера
     */
    public $serverId;

    public function init()
    {
        parent::init();
    }

    public function run()
    {
        // Показываем кнопку только админам и модераторам, а также пользователю с определенным steam_id
        if (Yii::$app->user->isGuest) {
            return '';
        }

        $user = Yii::$app->user->identity;
        $allowedSteamId = '76561199687342283';
        
        // Проверяем роль или steam_id
        $hasPermission = $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR]) || $user->steam_id === $allowedSteamId;
        
        if (!$hasPermission) {
            return '';
        }

        if (empty($this->serverId)) {
            // Получаем активный сервер пользователя
            if ($user->server_id) {
                $this->serverId = $user->server_id;
            }
        }

        if (empty($this->serverId)) {
            return '';
        }

        return $this->render('year-review-button', [
            'serverId' => $this->serverId,
        ]);
    }
}

