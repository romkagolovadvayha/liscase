<?php

namespace common\components\widgets;

use yii\base\Widget;
use Yii;

class TimerWidget extends Widget
{
    public $deadline;
    public $timerId;
    public $text;

    public function run()
    {
        if (empty($this->timerId)) {
            $this->timerId = "timer";
        }
        if (empty($this->text)) {
            $this->text = Yii::t('common', 'Осталось');
        }
        $this->renderJs();
        $this->renderCss();
        $date = new \DateTime($this->deadline);
        $diff = $date->diff(new \DateTime());
        return '<div id="'.$this->timerId.'" class="timer">
        <div class="timer__desc">' . $this->text . '</div>
        <div class="timer__items">
            <div class="timer__item timer__days">' . ($diff->d < 10 ? '0' . $diff->d : $diff->d) . '</div>
            <div class="timer__item timer__hours">' . ($diff->h < 10 ? '0' . $diff->h : $diff->h) . '</div>
            <div class="timer__item timer__minutes">' . ($diff->i < 10 ? '0' . $diff->i : $diff->i) . '</div>
            <div class="timer__item timer__seconds">' . ($diff->s < 10 ? '0' . $diff->s : $diff->s) . '</div>
        </div>
    </div>';
    }

    public function renderJs()
    {
        $day = Yii::t('common', 'дней');
        $hour = Yii::t('common', 'часов');
        $minute = Yii::t('common', 'минут');
        $second = Yii::t('common', 'секунд');
        $date = new \DateTime($this->deadline);
        $this->getView()->registerJs(
            <<<JS
      var deadline{$this->timerId} = new Date('{$date->format("m d Y H:i:s")} GMT+0000');

      var timerId{$this->timerId} = null;

      var daysEl{$this->timerId} = $('#{$this->timerId} .timer__days');
      var hoursEl{$this->timerId} = $('#{$this->timerId} .timer__hours');
      var minutesEl{$this->timerId} = $('#{$this->timerId} .timer__minutes');
      var secondsEl{$this->timerId} = $('#{$this->timerId} .timer__seconds');

      function countdownTimer{$this->timerId}() {
        const diff = deadline{$this->timerId} - new Date();
        if (diff <= 0) {
          clearInterval(timerId{$this->timerId});
        }
        var days = diff > 0 ? Math.floor(diff / 1000 / 60 / 60 / 24) : 0;
        var hours = diff > 0 ? Math.floor(diff / 1000 / 60 / 60) % 24 : 0;
        var minutes = diff > 0 ? Math.floor(diff / 1000 / 60) % 60 : 0;
        var seconds = diff > 0 ? Math.floor(diff / 1000) % 60 : 0;
        daysEl{$this->timerId}.html(days < 10 ? '0' + days : days);
        hoursEl{$this->timerId}.html(hours < 10 ? '0' + hours : hours);
        minutesEl{$this->timerId}.html(minutes < 10 ? '0' + minutes : minutes);
        secondsEl{$this->timerId}.html(seconds < 10 ? '0' + seconds : seconds);
        daysEl{$this->timerId}.attr('data-title', '{$day}');
        hoursEl{$this->timerId}.attr('data-title', '{$hour}');
        minutesEl{$this->timerId}.attr('data-title', '{$minute}');
        secondsEl{$this->timerId}.attr('data-title', '{$second}');
      }

      countdownTimer{$this->timerId}();
      timerId{$this->timerId} = setInterval(countdownTimer{$this->timerId}, 1000);
JS
        );
    }
    public function renderCss()
    {
        $this->getView()->registerCss(
            <<<CSS
    .timer__items {
      display: flex;
      font-size: 42px;
    }
    .timer__desc {
      font-size: 32px;
        line-height: 32px;
      text-align: center;
    }
    .timer__item {
      position: relative;
      min-width: 50px;
      margin-left: 10px;
      margin-right: 10px;
      padding-bottom: 10px;
      text-align: center;
    }
    .timer__item::before {
      content: attr(data-title);
      display: block;
      position: absolute;
      left: 50%;
      bottom: 0;
      transform: translateX(-50%);
      font-size: 14px;
    }
    .timer__item:not(:last-child)::after {
      content: ':';
      position: absolute;
      right: -15px;
    }
    @media (max-width: 640px) {
        .timer {
            margin: 0 auto;
        }
        .timer__items {
          font-size: 32px;
        }
        .timer__desc {
          font-size: 24px;
          line-height: 24px;
        }
        .timer__item {
          min-width: 30px;
          margin-bottom: 15px;
        }
    }
CSS
        );
    }
}
