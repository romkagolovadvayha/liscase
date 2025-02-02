// Import our custom CSS
// import "../scss/styles.scss";
//
// // Import only the Bootstrap components we need
// import { Popover } from "bootstrap";
//
// import "./menu";
// import "./copy";
// import "./language";

// Create an example popover
document.querySelectorAll('[data-bs-toggle="popover"]').forEach((popover) => {
    new Popover(popover);
});
//
// document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((tooltip) => {
//     new bootstrap.Tooltip(tooltip);
// });

// КАТЕГОРИИ
$(document).ready(function () {
    $(".owl-carousel").owlCarousel({
        loop: false,
        items: 6,
        stagePadding: 40,
        margin: 12,
        autoWidth: true,
        checkVisible: true,
        // nav: true,
        // navText: [
        //   "<i class='fa fa-caret-left'></i>",
        //   "<i class='fa fa-caret-right'></i>"
        // ],
    });
});

$(document).on('pjax:send', function() {
    $('#product-loader').addClass('active');
    $('#buy_product').attr('aria-disabled', true);
    $('#skin_loader').addClass('active');
});
$(document).on('pjax:complete', function() {
    $('#product-loader').removeClass('active');
    $('#skin_loader').removeClass('active');
    if (document.getElementById('crypto-payment-form')) {
        document.getElementById('crypto-payment-form').querySelectorAll('[data-bs-toggle="tooltip"]').forEach((tooltip) => {
            new bootstrap.Tooltip(tooltip);
        });
    }
});
var categories = $('.categories .category');
var more_products_button = $('#more_products');
var more_sets_button = $('#more_sets');
// var servers_block_button = $('.servers_block');
window.currentCategoryId = undefined;
// categories.first().addClass('products_categories_category_active');
window.search = function() {
    var input, filter, ul, li, a, i, txtValue, categoryId;
    input = document.getElementById("search");
    filter = input.value.toUpperCase();
    ul = document.getElementById("products");
    li = ul.querySelectorAll(".category-card");
    for (i = 0; i < li.length; i++) {
        txtValue = $(li[i]).attr('data-title');
        categoryId = $(li[i]).attr('data-category-id');
        if (filter.length === 0 && (window.currentCategoryId === '' || window.currentCategoryId === undefined) && i > 24) {
            li[i].style.display = "none";
            continue;
        }
        if (txtValue.toUpperCase().indexOf(filter) > -1 && (window.currentCategoryId === '' || window.currentCategoryId === undefined || categoryId === window.currentCategoryId)) {
            li[i].style.display = "";
        } else {
            li[i].style.display = "none";
        }
    }
    if (filter.length === 0 && (window.currentCategoryId === '' || window.currentCategoryId === undefined)) {
        more_products_button.show();
    } else {
        more_products_button.hide();
    }
}
categories.click(function () {
    var categories = $('.categories .category.category_active');
    categories.removeClass('category_active');
    if (window.currentCategoryId !== $(this).attr('data-id')) {
        $(this).addClass('category_active');
        window.currentCategoryId = $(this).attr('data-id');
    } else {
        window.currentCategoryId = undefined;
    }
    search();
});
more_products_button.click(function () {
    $('#products .category-card').show();
    more_products_button.hide();
});

more_sets_button.click(function () {
    $('.sets .set').show();
    more_sets_button.hide();
});

var notificationSound = undefined;
function sound(file, hash) {
    notificationSound = new Audio(file);
    notificationSound.volume = 0.5;  // Устанавливаем громкость от 0 до 1
    notificationSound.loop = false;  // Повторять ли звук

    // Проверяем, если на других вкладках уже был запущен звук
    if (localStorage.getItem('soundPlayed') === 'true') {
        console.log('Звук уже воспроизведен на другой вкладке.');
        return;
    }
    localStorage.setItem('soundPlayed', 'true');

    notificationSound.play().catch((error) => {
        console.error('Ошибка при воспроизведении звука:', error);
    });

    // Обработчик события завершения воспроизведения
    notificationSound.onended = () => {
        // Когда звук заканчивается, сбрасываем флаг
        localStorage.setItem('soundPlayed', 'false');
        notificationSound = undefined;
    };
}
// Слушаем событие изменения localStorage (для других вкладок)
window.addEventListener('storage', (event) => {
    if (event.key === 'soundPlayed' && event.newValue === 'true') {
        if (notificationSound !== undefined) {
            notificationSound.pause();
            notificationSound.currentTime = 0;
        }
    }
});
// Сбрасываем флаг при закрытии вкладки или ее перезагрузке
window.addEventListener('beforeunload', () => {
    // Сбрасываем флаг в localStorage
    if (localStorage.getItem('soundPlayed') === 'true') {
        notificationSound = undefined;
        localStorage.setItem('soundPlayed', 'false');
    }
});
// servers_block_button.click(function () {
//     $(this).toggleClass('noactive');
// });
$(document).ready(function() {
    // Обработчик для кнопки + (голос за)
    $('.vote-up').on('click', function() {
        var mapId = $(this).data('id'); // Получаем ID карты
        var vote = 1; // Голос за
        voteForMap(mapId, vote);
    });

    // Обработчик для кнопки - (голос против)
    $('.vote-down').on('click', function() {
        var mapId = $(this).data('id'); // Получаем ID карты
        var vote = -1; // Голос против
        voteForMap(mapId, vote);
    });

    // Функция для отправки голосов через AJAX
    function voteForMap(mapId, vote) {
        $.ajax({
            url: '/voting/vote?map_id=' + mapId + '&vote=' + vote,  // URL для голосования
            type: 'POST',
            data: {},
        success: function(response) {
            // Если голосование успешно, обновляем страницу или перерисовываем статистику
            // Можно обновить статистику голосов или перезагрузить страницу
            alert('Голос успешно отправлен!');
            location.reload(); // Перезагружаем страницу, чтобы обновить голоса
        },
        error: function() {
            alert('Произошла ошибка. Попробуйте снова.');
        }
    });
    }
});

var timers = $('.wipe_timer');
for (var i = 0; i < timers.length; i++) {
    var dateTime = $(timers[i]).attr('data-time');
    var left = moment.unix(dateTime);
    $(timers[i]).html(left.locale(lang).format('D MMMM H:mm'));
}
function payClockdown(deadline) {
    if (!$('.clockdown_minutes').length || deadline <= 0) {
        return;
    }
    let minutes = parseInt(deadline / 60);
    let seconds = parseInt(deadline % 60);
    if (minutes < 10) {
        minutes = '0' + minutes;
    }
    if (seconds < 10) {
        seconds = '0' + seconds;
    }
    $('.clockdown_minutes').html(minutes);
    $('.clockdown_seconds').html(seconds);
    setTimeout(() => {
        payClockdown(deadline - 1);
    }, 1000);
}