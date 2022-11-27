<?php

namespace common\components\grid;

use Yii;
use yii\bootstrap5\Html;

class ManageButton
{
    const BTN_CLASS = 'btn btn-sm';

    public static function view($url)
    {
        return Html::a(Yii::t('common', 'Просмотр'), $url, [
            'title' => Yii::t('common', 'Просмотр'),
            'class' => self::BTN_CLASS . ' btn-default',
        ]);
    }

    public static function update($url)
    {
        return Html::a(Yii::t('common', 'Редактирование'), $url, [
            'title' => Yii::t('common', 'Редактирование'),
            'class' => self::BTN_CLASS . ' btn-default',
        ]);
    }

    public static function delete($url, $confirm = null)
    {
        if ($confirm !== false && $confirm === null) {
            $confirm = Yii::t('common', 'Вы уверены, что хотите удалить эту запись?');
        }

        return Html::a(Yii::t('common', 'Удаление'), $url, [
            'title'        => Yii::t('common', 'Удаление'),
            'data-confirm' => $confirm,
            'class'        => self::BTN_CLASS . ' btn-default',
        ]);
    }

    public static function manage($url)
    {
        return Html::a(Yii::t('common', 'Настройка'), $url, [
            'title' => Yii::t('common', 'Настройка'),
            'class' => self::BTN_CLASS . ' btn-primary',
        ]);
    }

    public static function create($title, $url = ['create'])
    {
        return Html::a($title, $url,
            ['class' => self::BTN_CLASS . ' btn-success']);
    }

    public static function success($url, $confirm)
    {
        return Html::a(Yii::t('common', 'OK'), $url, [
            'class'        => self::BTN_CLASS . ' btn-default',
            'data-confirm' => $confirm,
        ]);
    }
}