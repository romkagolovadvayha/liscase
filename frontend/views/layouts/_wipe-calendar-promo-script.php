<?php
/**
 * Общий скрипт для автоматического открытия модального окна с промокодом
 * Используется на страницах: /wipe-calendar, /posts и подстраницах категорий постов
 */
$isGuest = Yii::$app->user->isGuest;
?>

<script>
    // Передаем информацию о том, является ли пользователь гостем
    const IS_GUEST = <?= $isGuest ? 'true' : 'false' ?>;
    
    // Проверка, является ли пользователь новым посетителем и открытие модального окна
    function initWipeCalendarPromo() {
        // Проверяем, является ли пользователь гостем
        if (!IS_GUEST) {
            // Если пользователь авторизован, не показываем модальное окно
            return;
        }
        
        // Проверяем, был ли пользователь уже на этой странице
        const cookieName = 'wipe_calendar_visited';
        const visited = getCookie(cookieName);
        
        // Проверяем, пришел ли пользователь из поиска (referrer содержит поисковые системы)
        const referrer = document.referrer || '';
        const isFromSearch = referrer && (
            referrer.indexOf('prostoj.') === -1
        );
        
        // Проверяем, был ли пользователь на сайте ранее (общая cookie)
        const siteVisited = getCookie('site_visited');
        
        // Если пользователь еще не был на странице, пришел из поиска и это его первое посещение сайта
        if (!visited && isFromSearch && !siteVisited) {
            // Устанавливаем cookie на 1 год
            setCookie(cookieName, '1', 365);
            setCookie('site_visited', '1', 365);
            
            // Открываем модальное окно автоматически через 5 секунд
            setTimeout(function() {
                const modalLink = document.createElement('div');
                modalLink.className = 'show-modal-link';
                modalLink.setAttribute('data-href', '/site/wipe-calendar-promo');
                modalLink.setAttribute('data-size', 'modal-sm');
                modalLink.setAttribute('data-toggl', 'modal');
                modalLink.setAttribute('data-target', 'modal-dialog');
                modalLink.style.display = 'none';
                document.body.appendChild(modalLink);
                
                // Триггерим клик для открытия модального окна
                modalLink.click();
                
                // Удаляем элемент после использования
                setTimeout(function() {
                    document.body.removeChild(modalLink);
                }, 100);
            }, 4000);
        } else if (!visited) {
            // Если пользователь просто впервые на странице (не из поиска или уже был на сайте)
            // Устанавливаем cookie, но не открываем модальное окно
            setCookie(cookieName, '1', 365);
        }
    }

    // Функции для работы с cookie
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    }

    function getCookie(name) {
        const nameEQ = name + '=';
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Инициализация при загрузке страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWipeCalendarPromo);
    } else {
        initWipeCalendarPromo();
    }
    
    // Инициализация после PJAX-подгрузки
    document.addEventListener('pjax:end', function () {
        initWipeCalendarPromo();
    });
</script>

