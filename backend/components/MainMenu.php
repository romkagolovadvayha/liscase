<?php

namespace backend\components;

use Yii;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use common\components\helpers\Role;

class MainMenu
{

    /**
     * @return array
     */
    public function getMenuItems()
    {
        if (Yii::$app->user->isGuest) {
            $array = [
                [
                    'label' => 'Login',
                    'icon'  => 'bi bi-box-arrow-in-right',
                    'url'   => ['/auth/login'],
                ],
            ];

            return $this->_prepareArray($array);
        }

        if (!Yii::$app->user->identity->isAccessBackend()) {
            $array = [
                [
                    'label' => Yii::t('common', Yii::$app->user->identity->email, [
                        'email' => Yii::$app->user->identity->email,
                    ]),
                    'icon'  => 'log-out',
                    'url'   => Url::to(Yii::$app->params['baseUrl']),
                ],
            ];

            return $this->_prepareArray($array);
        }

        $isAdmin            = Yii::$app->user->can(Role::ROLE_ADMIN);

        if ($isAdmin) {
            $array = $this->_getAdminMenuItems();
        }

        $array[] = [
            'label' => 'Выход',
            'icon'  => 'bi bi-box-arrow-left',
            'url'   => '/auth/logout',
        ];

        return $this->_prepareArray($array);
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function _prepareArray(array $array)
    {
        foreach ($array as &$firstLine) {
            if (!empty($firstLine['icon'])) {
                $firstLine['label'] = Html::tag('i', '', ['class' => $firstLine['icon']]) . ' ' . $firstLine['label'];
            }

            if (!empty($firstLine['badge'])) {
                $firstLine['label'] .= '&nbsp;' . Html::tag('span', $firstLine['badge'],
                        ['class' => 'label label-rounded label-danger']);
            }

            if (empty($firstLine['items'])) {
                continue;
            }

            foreach ($firstLine['items'] as &$secondLine) {
                if (!empty($secondLine['icon'])) {
                    $secondLine['label'] = Html::tag('i', '', ['class' => $secondLine['icon']]) . ' ' . $secondLine['label'];
                }

                if (empty($secondLine['badge'])) {
                    continue;
                }

                $secondLine['label'] .= '&nbsp;' . Html::tag('span', $secondLine['badge'],
                        ['class' => 'label label-rounded label-danger']);
            }
            unset($secondLine);
        }
        unset($firstLine);

        return $array;
    }

    /**
     * @return array
     */
    private function _getAdminMenuItems()
    {

        return [
            [
                'label' => Yii::t('common', 'Пользователи'),
                'icon'  => 'bi bi-people',
                'items' => [
                    [
                        'label'  => Yii::t('common', 'Пользователи'),
                        'url'    => '/user/index',
                        'active' => $this->_checkActive('/user/'),
                    ],
                ],
            ],
            [
                'label'  => Yii::t('common', 'Блог'),
                'icon'   => 'bi bi-newspaper',
                'url'    => '/blog/index',
                'active' => $this->_checkActive('/blog/'),
            ],
            [
                'label'  => Yii::t('common', 'Мультиязычность'),
                'icon'   => 'bi bi-shield-fill',
                'url'    => '/translateManager/language/list',
                'active' => $this->_checkActive('/translatemanager/'),
            ],
            [
                'label' => Yii::t('common', 'Администрирование'),
                'icon'  => 'bi bi-shield-fill',
                'badge' => 0,
                'items' => [
                    [
                        'label'  => Yii::t('common', 'Настройки'),
                        'url'    => '/settings/index',
                        'active' => $this->_checkActive('/settings/index'),
                    ],
                    [
                        'label'  => Yii::t('common', 'Внешний вид'),
                        'url'    => '/settings/theme',
                        'active' => $this->_checkActive('/settings/theme'),
                    ],
                ],

            ]
        ];
    }

    /**
     * @param string $urlStr
     *
     * @return bool
     */
    private function _checkActive($urlStr)
    {
        return (bool)strstr(Yii::$app->request->url, $urlStr);
    }
}