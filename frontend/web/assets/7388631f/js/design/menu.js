// Основное меню.
var main_menu = document.getElementById("main-menu");
// Скрыть/показать меню.
var menu_toggle_button = document.getElementById("menu-toggle-button");
// Блок социальные сети.
var social_network_from_menu = $('.menu__social-network_wrap');
// Все названия пунктов меню.
var main_menu_text_list = $('.main-menu-text');
var main_list_wrap = $('.menu__list_wrap');
// Блок с контентом.
var main = $('.main');
// Все индикаторы уведомлений.
var main_menu_notification = $('.main-menu-notification');

// Устаналиваем стили для режима краткое меню.
function handleHideMenu() {
    main_menu.style.width = "102px";
    social_network_from_menu.hide();
    main_menu_text_list.hide();
    main_list_wrap.attr('style', 'margin-top: 40px');
    main_menu_notification.addClass('mini-notification');

    main.addClass("main_menu_hide");
}

// Сбрасываем стили в дефолтное значение.
function handleShowMenu() {
    main_menu.style.width = "";
    social_network_from_menu.show();
    main_menu_text_list.show();
    main_menu_notification.removeClass('mini-notification');
    main_list_wrap.attr('style', '');

    main.removeClass("main_menu_hide");
}

// Отслеживаем каждый клик на кнопку скрыть/показать меню.
menu_toggle_button.addEventListener("click", () => {
    $.ajax({
        type: "GET",
        url: "/site/menu-toggle"
    });

    if (main.hasClass('main_menu_hide')) {
        return handleShowMenu();
    } else {
        return handleHideMenu();
    }
});