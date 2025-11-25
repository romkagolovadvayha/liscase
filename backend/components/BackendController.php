<?php

namespace backend\components;

use yii\web\Controller;

/**
 * Базовый контроллер для всех backend контроллеров
 * CSRF валидация отключена глобально в конфигурации для dev окружения
 */
class BackendController extends Controller
{
}

