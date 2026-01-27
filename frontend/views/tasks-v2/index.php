<?php

use frontend\widgets\Alert;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ListView;

/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \common\models\user\User $user */
/** @var string|null $currentType */
/** @var string|null $currentStatus */
/** @var string|null $currentSort */
/** @var int $totalTasks */
/** @var int $completedTasks */
/** @var int $completionPercent */
/** @var float $totalCoins */
/** @var int $totalRewards */

$this->title = Yii::t('common', 'Задания');

?>
<div class="tasksV2" id="tasksV2Root">
    <header class="tasksV2__hero">
        <div class="tasksV2__hero-primary">
            <p class="tasksV2__eyebrow"><?= Yii::t('common', 'Система заданий v2') ?></p>
            <h1 class="tasksV2__title">
                <?= Yii::t('common', 'Задания и награды') ?>
            </h1>
            <p class="tasksV2__subtitle">
                <?= Yii::t('common', 'Выполняйте задания и получайте награды') ?>
            </p>
        </div>
    </header>

    <?= Alert::widget() ?>

    <!-- Блок статистики заданий -->
    <div class="tasksV2__stats">
        <div class="tasksV2__stats-header">
            <h2 class="tasksV2__stats-title">
                <?= Yii::t('common', 'Выполните все задания и получите {coins} монет и {rewards} наград', [
                    'coins' => '<span class="tasksV2__stats-highlight">' . number_format($totalPotentialCoins, 0, '.', ' ') . '</span>',
                    'rewards' => '<span class="tasksV2__stats-highlight">' . $totalPotentialRewards . '</span>'
                ]) ?>
            </h2>
        </div>
        <div class="tasksV2__stats-progress-wrapper">
            <div class="tasksV2__stats-progress-bar">
                <div class="tasksV2__stats-progress-fill" style="width: <?= $completionPercent ?>%"></div>
            </div>
        </div>
        <div class="tasksV2__stats-footer">
            <div class="tasksV2__stats-item">
                <span class="tasksV2__stats-label"><?= Yii::t('common', 'Выполнено') ?></span>
                <span class="tasksV2__stats-value"><?= $completedTasks ?> / <?= $totalTasks ?></span>
            </div>
            <div class="tasksV2__stats-item">
                <span class="tasksV2__stats-label"><?= Yii::t('common', 'Получено монет') ?></span>
                <span class="tasksV2__stats-value"><?= number_format($totalCoins, 0, '.', ' ') ?></span>
            </div>
            <div class="tasksV2__stats-item">
                <span class="tasksV2__stats-label"><?= Yii::t('common', 'Получено наград') ?></span>
                <span class="tasksV2__stats-value"><?= $totalRewards ?></span>
            </div>
        </div>
    </div>

    <div class="tasksV2__layout">
        <div id="tasks-list" data-role="tasks-list">
            <?= ListView::widget([
                'dataProvider' => $dataProvider,
                'itemView' => '_card',
                'viewParams' => [
                    'user' => $user,
                    'tasksProgress' => $tasksProgress ?? [],
                ],
                'options' => ['class' => 'tasksV2__cards'],
                'summary' => false,
                'layout' => '{items}{pager}',
                'pager' => [
                    'options' => ['class' => 'pagination'],
                    'linkOptions' => ['data-pjax' => 1],
                    'maxButtonCount' => 0,
                    'nextPageLabel' => Yii::t('common', 'Показать ещё'),
                    'prevPageLabel' => false,
                ],
                'emptyText' => '<section class="tasksV2__empty">
                    <div class="tasksV2__empty-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h2>' . Yii::t('common', 'Задания не найдены') . '</h2>
                    <p>' . Yii::t('common', 'В данный момент нет доступных заданий.') . '</p>
                </section>',
            ]) ?>
            
            <!-- Сентинел для ленивой подгрузки -->
            <div id="lazy-trigger" aria-hidden="true"></div>
        </div>
    </div>
</div>

<style>
    #tasks-list .pagination { display: none; }
    #lazy-trigger {
        height: 1px;
        margin: 1px auto 0;
        visibility: hidden;
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    #lazy-trigger.loading {
        visibility: visible;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 64px;
        width: 100%;
    }
    #lazy-trigger.loading::after {
        content: '';
        display: block;
        margin-top: 12px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 5px solid var(--background-teritiary);
        border-top-color: var(--primary-colors-main);
        animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<?php
$js = <<<JS
(function(){
  var loading = false;
  var observer = null;
  var loadedPages = new Set();

  function getNextUrl(){
    // Пробуем разные варианты селекторов для пагинации
    var \$a = $('#tasks-list .pagination a[rel="next"], #tasks-list .pagination li.next a, #tasks-list .pagination .next a');
    if (\$a.length) {
      var href = \$a.attr('href');
      console.log('Found next URL via rel="next":', href);
      return href;
    }
    // Ищем ссылку с текстом "Показать ещё"
    \$a = $('#tasks-list .pagination a').filter(function() {
      var text = $(this).text().trim();
      return text === 'Показать ещё' || text.indexOf('Показать') !== -1;
    });
    if (\$a.length) {
      var href = \$a.attr('href');
      console.log('Found next URL via text:', href);
      return href;
    }
    // Если не нашли, ищем любую ссылку в пагинации (последняя страница не имеет ссылок)
    var \$pager = $('#tasks-list .pagination');
    if (\$pager.length && \$pager.find('a').length > 0) {
      // Берем последнюю ссылку (обычно это следующая страница)
      var \$lastLink = \$pager.find('a').last();
      var href = \$lastLink.attr('href');
      console.log('Found next URL via last link:', href);
      return href;
    }
    console.log('No next page URL found. Pager exists:', \$pager.length, 'Links count:', \$pager.find('a').length, 'Pager HTML:', \$pager.html());
    return null;
  }

  function requestPjaxFragment(url){
    return $.ajax({
      url: url,
      type: 'GET',
      cache: false
    });
  }

  function ensureTriggerAtBottom(){
    var \$wrap = $('#tasks-list .tasksV2__cards');
    if (\$wrap.length) {
      $('#lazy-trigger').appendTo(\$wrap);
    } else {
      $('#lazy-trigger').appendTo('#tasks-list');
    }
  }

  function loadNext(){
    if (loading) {
      console.log('Already loading...');
      return;
    }
    var url = getNextUrl();
    if (!url) {
      console.log('No next URL available');
      return;
    }
    
    if (loadedPages.has(url)) {
      console.log('Page already loaded:', url);
      return;
    }

    console.log('Loading page:', url);
    loading = true;
    loadedPages.add(url);
    $('#lazy-trigger').addClass('loading');
    
    if (observer) {
      observer.disconnect();
    }

    requestPjaxFragment(url).done(function(html){
      console.log('Page loaded, processing HTML...');
      
      // Парсим HTML ответ - пробуем разные способы
      var \$html = $('<div>').html(html);
      
      // Если это полная страница, ищем в body
      var \$body = \$html.find('body');
      if (\$body.length) {
        \$html = \$body;
      }

      // новые карточки - ищем в основном контенте
      var \$newItemsWrap = \$html.find('#tasks-list .tasksV2__cards');
      if (!\$newItemsWrap.length) {
        \$newItemsWrap = \$html.find('.tasksV2__cards');
      }
      if (!\$newItemsWrap.length) {
        // Если не нашли, возможно HTML содержит только часть страницы
        \$newItemsWrap = \$html.filter('.tasksV2__cards');
      }
      
      // Пробуем найти элементы article напрямую
      var \$newItems = null;
      if (\$newItemsWrap.length) {
        // Сначала пробуем children (прямые потомки)
        \$newItems = \$newItemsWrap.children('article');
        
        // Если не нашли через children, пробуем find (все потомки)
        if (!\$newItems.length) {
          \$newItems = \$newItemsWrap.find('article');
        }
      }
      
      // Если все еще не нашли, пробуем найти все article в HTML
      if (!\$newItems || !\$newItems.length) {
        \$newItems = \$html.find('article');
        console.log('Trying to find all articles in HTML:', \$newItems.length);
      }
      
      console.log('Found new items:', \$newItems ? \$newItems.length : 0, 'Container found:', \$newItemsWrap.length, 'HTML length:', html.length);
      
      // Отладочная информация - показываем структуру контейнера
      if (\$newItemsWrap.length) {
        var children = \$newItemsWrap.children();
        console.log('Container children count:', children.length);
        console.log('Container children tags:', Array.from(children).map(function(el) { return el.tagName; }).join(', '));
        if (children.length > 0) {
          console.log('First child HTML (first 500 chars):', children[0].outerHTML.substring(0, 500));
        }
        console.log('Container HTML structure (first 1000 chars):', \$newItemsWrap[0].outerHTML.substring(0, 1000));
      } else {
        // Если контейнер не найден, показываем что есть в HTML
        console.log('Container not found! Searching in full HTML...');
        console.log('HTML contains #tasks-list:', \$html.find('#tasks-list').length);
        console.log('HTML contains .tasksV2__cards:', \$html.find('.tasksV2__cards').length);
        console.log('HTML contains article:', \$html.find('article').length);
        console.log('First 2000 chars of HTML:', html.substring(0, 2000));
      }
      
      // Если элементы не найдены, выходим
      if (!\$newItems || !\$newItems.length) {
        console.error('No items found in response!');
        loading = false;
        $('#lazy-trigger').removeClass('loading');
        return;
      }

      // Проверяем на дубликаты
      var existingIds = new Set();
      $('#tasks-list .tasksV2__cards article, #tasks-list article').each(function(){
        var itemId = $(this).attr('data-task-id');
        if (itemId) {
          existingIds.add(itemId);
        }
      });

      // Фильтруем дубликаты
      var \$uniqueItems = \$newItems.filter(function(){
        var itemId = $(this).attr('data-task-id');
        return !itemId || !existingIds.has(itemId);
      });

      // новая пагинация
      var \$newPager = \$html.find('#tasks-list .pagination');
      if (!\$newPager.length) {
        \$newPager = \$html.find('.pagination');
      }
      
      console.log('New pager found:', \$newPager.length, 'Items found:', \$uniqueItems.length);

      // добавляем уникальные элементы
      var \$container = $('#tasks-list .tasksV2__cards');
      if (!\$container.length) {
        \$container = $('#tasks-list');
      }
      
      if (\$uniqueItems.length > 0) {
        console.log('Appending', \$uniqueItems.length, 'items');
        \$container.append(\$uniqueItems);
      } else {
        console.log('No unique items to append');
      }

      // заменяем пагинацию
      var \$oldPager = $('#tasks-list .pagination');
      if (\$newPager.length) {
        \$oldPager.replaceWith(\$newPager);
        console.log('Pager replaced');
      } else {
        \$oldPager.remove();
        console.log('Pager removed (no more pages)');
      }

      ensureTriggerAtBottom();
      
      var nextUrl = getNextUrl();
      if (nextUrl) {
        console.log('Next URL available:', nextUrl);
        bindObserver();
      } else {
        console.log('No more pages to load');
      }
    }).fail(function(){
      loadedPages.delete(url);
    }).always(function(){
      loading = false;
      $('#lazy-trigger').removeClass('loading');
    });
  }

  function bindObserver(){
    if (observer) { 
      observer.disconnect(); 
      observer = null; 
    }
    
    var target = document.getElementById('lazy-trigger');
    if (!target) return;

    observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting && !loading) {
          var nextUrl = getNextUrl();
          if (nextUrl) {
            console.log('Loading next page:', nextUrl);
            loadNext();
          } else {
            console.log('No next page available');
          }
        }
      });
    }, {
      rootMargin: '400px 0px',
      threshold: 0
    });

    observer.observe(target);
  }

  // Инициализация при загрузке страницы
  $(document).ready(function() {
    setTimeout(function() {
      ensureTriggerAtBottom();
      bindObserver();
      console.log('Lazy loader initialized. Next URL:', getNextUrl());
    }, 100);
  });
  
  // Переинициализация после PJAX обновлений
  $(document).on('pjax:success', '#tasksV2Root', function(){
    loadedPages.clear();
    setTimeout(function() {
      ensureTriggerAtBottom();
      bindObserver();
      initDailyRewardTimers();
    }, 100);
  });
})();
JS;
$this->registerJs($js);

// Таймер обратного отсчета для ежедневных наград
$timerJs = <<<JS
(function(){
  function initDailyRewardTimers() {
    $('.tasksV2__card-badge--timer').each(function() {
      var \$badge = $(this);
      var \$text = \$badge.find('.tasksV2__card-timer-text');
      var nextReset = parseInt(\$badge.data('next-reset'));
      
      if (!nextReset || isNaN(nextReset)) {
        return;
      }
      
      function updateTimer() {
        var now = moment();
        var resetTime = moment.unix(nextReset);
        var diff = resetTime.diff(now);
        
        if (diff <= 0) {
          \$text.text('00:00:00');
          // Перезагружаем страницу, чтобы обновить статус
          setTimeout(function() {
            location.reload();
          }, 1000);
          return;
        }
        
        var duration = moment.duration(diff);
        var hours = Math.floor(duration.asHours());
        var minutes = duration.minutes();
        var seconds = duration.seconds();
        
        var timeString = 
          String(hours).padStart(2, '0') + ':' +
          String(minutes).padStart(2, '0') + ':' +
          String(seconds).padStart(2, '0');
        
        \$text.text(timeString);
      }
      
      // Обновляем сразу
      updateTimer();
      
      // Обновляем каждую секунду
      var intervalId = setInterval(updateTimer, 1000);
      
      // Сохраняем interval ID в элементе для возможности очистки
      \$badge.data('timer-interval', intervalId);
    });
  }
  
  // Инициализация при загрузке страницы
  $(document).ready(function() {
    initDailyRewardTimers();
  });
  
  // Переинициализация после PJAX обновлений
  $(document).on('pjax:success', '#tasksV2Root', function(){
    setTimeout(function() {
      initDailyRewardTimers();
    }, 100);
  });
})();
JS;
$this->registerJs($timerJs);
?>



