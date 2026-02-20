<?php

namespace backend\controllers;

use Yii;
use yii\web\Controller;
use common\components\helpers\Role;

/**
 * Контроллер для демонстрации дизайн-системы
 */
class DesignSystemController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
        ];
    }

    /**
     * Главная страница дизайн-системы с навигацией
     * @return string
     */
    public function actionIndex()
    {
        $this->view->title = 'Дизайн-система';
        return $this->render('index');
    }

    /**
     * Цветовая палитра
     * @return string
     */
    public function actionColors()
    {
        $this->view->title = 'Цветовая палитра - Дизайн-система';
        return $this->render('colors');
    }

    /**
     * Типографика
     * @return string
     */
    public function actionTypography()
    {
        $this->view->title = 'Типографика - Дизайн-система';
        return $this->render('typography');
    }

    /**
     * Кнопки
     * @return string
     */
    public function actionButtons()
    {
        $this->view->title = 'Кнопки - Дизайн-система';
        return $this->render('buttons');
    }

    /**
     * Формы
     * @return string
     */
    public function actionForms()
    {
        $this->view->title = 'Формы - Дизайн-система';
        return $this->render('forms');
    }

    /**
     * Карточки
     * @return string
     */
    public function actionCards()
    {
        $this->view->title = 'Карточки - Дизайн-система';
        return $this->render('cards');
    }

    /**
     * Таблицы
     * @return string
     */
    public function actionTables()
    {
        $this->view->title = 'Таблицы - Дизайн-система';
        return $this->render('tables');
    }

    /**
     * Модальные окна
     * @return string
     */
    public function actionModals()
    {
        $this->view->title = 'Модальные окна - Дизайн-система';
        return $this->render('modals');
    }

    /**
     * Навигация
     * @return string
     */
    public function actionNavigation()
    {
        $this->view->title = 'Навигация - Дизайн-система';
        return $this->render('navigation');
    }

    /**
     * Обратная связь (Alerts, Toasts, Badges)
     * @return string
     */
    public function actionFeedback()
    {
        $this->view->title = 'Обратная связь - Дизайн-система';
        return $this->render('feedback');
    }

    /**
     * Макет и структура страниц
     * @return string
     */
    public function actionLayout()
    {
        $this->view->title = 'Макет - Дизайн-система';
        return $this->render('layout');
    }

    /**
     * Мобильная версия
     * @return string
     */
    public function actionMobile()
    {
        $this->view->title = 'Мобильная версия - Дизайн-система';
        return $this->render('mobile');
    }
}
