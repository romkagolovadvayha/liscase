<?php

namespace frontend\widgets;

use Yii;

/**
 * Alert widget renders a message from session flash. All flash messages are displayed
 * in the sequence they were assigned using setFlash. You can set message as following:
 *
 * ```php
 * Yii::$app->session->setFlash('error', 'This is the message');
 * Yii::$app->session->setFlash('success', 'This is the message');
 * Yii::$app->session->setFlash('info', 'This is the message');
 * ```
 *
 * Multiple messages could be set as follows:
 *
 * ```php
 * Yii::$app->session->setFlash('error', ['Error 1', 'Error 2']);
 * ```
 *
 * @author Kartik Visweswaran <kartikv2@gmail.com>
 * @author Alexander Makarov <sam@rmcreative.ru>
 */
class Alert extends \yii\bootstrap5\Widget
{
    /**
     * @var array the alert types configuration for the flash messages.
     * This array is setup as $key => $value, where:
     * - key: the name of the session flash variable
     * - value: the bootstrap alert type (i.e. danger, success, info, warning)
     */
    public $alertTypes = [
        'error'   => 'error',
        'danger'  => 'error',
        'success' => 'success',
        'success-box' => 'success',
        'info'    => 'info',
        'warning' => 'warning'
    ];
    public $alertIcons = [
        'error'  => 'fas fa-exclamation-circle',
        'success' => 'fas fa-check-circle',
        'info'    => 'fas fa-info',
        'warning' => 'fas fa-exclamation'
    ];
    /**
     * @var array the options for rendering the close button tag.
     * Array will be passed to [[\yii\bootstrap\Alert::closeButton]].
     */
    public $closeButton = [];


    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $session = Yii::$app->session;

        foreach (array_keys($this->alertTypes) as $type) {
            $flash = $session->getFlash($type);

            foreach ((array) $flash as $i => $message) {
                $text = "<i class='{$this->alertIcons[$this->alertTypes[$type]]}'></i><div class='toast-message_text'>$message</div>";
                if ($type === 'success-box') {
                    $text = "<div class='toast-message_text'>$message</div>";
                }
                echo Notification::widget([
                                              'type' => $this->alertTypes[$type],
                                              'message' => $text,
                                              'options' => [
                                                  "progressBar" => true,
                                                  "positionClass" => Notification::POSITION_TOP_RIGHT,
                                                  "escapeHtml " => false,
                                              ]
                                          ]);
            }

            $session->removeFlash($type);
        }
    }
}
