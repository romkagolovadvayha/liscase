<?php

namespace common\components\helpers;

use yii\validators\EmailValidator;

class EmailHelper
{
    /**
     * Проверяет валидность email адреса
     * 
     * @param string $email Email адрес для проверки
     * @return bool
     */
    public static function isValid($email)
    {
        if (empty($email)) {
            return false;
        }

        $validator = new EmailValidator();
        return $validator->validate($email);
    }

    /**
     * Проверяет валидность email адреса (альтернативный метод с использованием filter_var)
     * 
     * @param string $email Email адрес для проверки
     * @return bool
     */
    public static function isValidSimple($email)
    {
        if (empty($email)) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

