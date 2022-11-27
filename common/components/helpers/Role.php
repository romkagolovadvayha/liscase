<?php

namespace common\components\helpers;

use common\models\user\User;
use Yii;

class Role
{
    const ROLE_ADMIN             = 'ADMIN';
    const ROLE_SUPPORT           = 'SUPPORT';
    const ROLE_CONTENT_MANAGER   = 'CONTENT_MANAGER';
    const ROLE_MARKETER          = 'MARKETER';
    const ROLE_TRANSLATE_MANAGER = 'TRANSLATE_MANAGER';
    const ROLE_FINANCIER         = 'FINANCIER';
    const ROLE_USER              = 'USER';
    const ROLE_SALES             = 'SALES';
    public const ROLE_COURSE_EDITOR = 'COURSE_EDITOR';
    const ROLE_VIDEO             = 'VIDEO';
    const ROLE_ACCOUNT_MANAGER   = 'ACCOUNT_MANAGER';

    /**
     * @return array
     */
    public static function getRoleList()
    {
        return [
            self::ROLE_ADMIN             => Yii::t('common', 'Администратор'),
            self::ROLE_SUPPORT           => Yii::t('common', 'Поддержка'),
            self::ROLE_CONTENT_MANAGER   => Yii::t('common', 'Контент-менеджер'),
            self::ROLE_TRANSLATE_MANAGER => Yii::t('common', 'Менеджер по переводам'),
            self::ROLE_MARKETER          => Yii::t('common', 'Маркетолог'),
            self::ROLE_FINANCIER         => Yii::t('common', 'Финансист'),
            self::ROLE_USER              => Yii::t('common', 'Пользователь'),
            self::ROLE_SALES             => Yii::t('common', 'Менеджер продаж'),
            self::ROLE_COURSE_EDITOR     => Yii::t('common', 'Редактор курсов'),
            self::ROLE_VIDEO             => Yii::t('common', 'Редактор видео'),
            self::ROLE_ACCOUNT_MANAGER   => Yii::t('common', 'Аккаунт менеджер'),
        ];
    }

    /**
     * @param $userId
     * @param array $rolesArr
     * @return void
     * @throws \Exception
     */
    public static function updateUserRoles($userId, array $rolesArr): void
    {
        if($userId && !empty($rolesArr)){
            Yii::$app->authManager->revokeAll($userId);
            $ifRoleUserNotExists = true;
            foreach ($rolesArr as $role){
                // если забыли роль USER выставить
                if($role === 'USER'){
                    $ifRoleUserNotExists = false;
                }
                $role = Yii::$app->authManager->getRole($role);
                Yii::$app->authManager->assign($role, $userId);
            }
            if($ifRoleUserNotExists){
                $role = Yii::$app->authManager->getRole('USER');
                Yii::$app->authManager->assign($role, $userId);
            }
        }
    }
}