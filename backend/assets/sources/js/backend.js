$(document).on('change', '#country-dashboard, #deposit-period, #expiration-period, #currency-type, #duration-period, #date-period, #fund-id', function () {
    $(this).closest('form').submit();
});
$(document).on('click', '.telegram_message_buttons_item_delete', function (e) {
    e.preventDefault();
    $(this).parent().remove();
});
var button_updated = undefined;
$(document).on('click', '.button_add', function (e) {
    e.preventDefault();
    button_updated = undefined;
    button_form_clear();
});
function updateButtonsText() {
    var buttons = $('.telegram_message_buttons_item');
    console.log(buttons);
    for (var k = 0; k < buttons.length; k++) {
        var value = $(buttons[k]).find('.button_title[data-language="' + current_language + '"]').val();
        if (!value.length) {
            value = "Пусто...";
        }
        $(buttons[k]).find('.telegram_message_buttons_item_title').html(value);
    }
}
if ($('.constructor_message_preview')) {
    var message_id = $('#telegramconstructor-telegram_constructor_message_id').val();
    if (message_id > 0) {
        updateMessagePreview();
    }
    $(document).on('change', '#telegramconstructor-telegram_constructor_message_id', function (e) {
        updateMessagePreview();
    });
}
function updateMessagePreview() {
    var message_id = $('#telegramconstructor-telegram_constructor_message_id').val();
    $.ajax({
        url: '/telegram-constructor-message/get-message-preview',
        method: 'GET',
        data: {
            id: message_id,
        },
        success: function(message_preview) {
            $('.constructor_message_preview').html(message_preview);
        }
    });
}
$(document).on('click', '.telegram_message_buttons_item_update', function (e) {
    e.preventDefault();
    button_updated = $(this).parent().parent();
    var button_titles = $(this).parent().find('.button_title');
    for (var k = 0; k < button_titles.length; k++) {
        $('.telegramConstructorMessageButtonTitle[data-language="' + button_titles[k].dataset.language + '"]').val(button_titles[k].value);
    }
    var messageId = $(this).parent().find('.button_messageId');
    if (messageId.length) {
        $('#telegramConstructorMessageButtonMessageId').val(messageId.val()).change();
    }
    var url = $(this).parent().find('.button_url');
    if (url.length) {
        $('#telegramConstructorMessageButtonUrl').val(url.val());
    }
});
function button_form_clear() {
    var url = $('#telegramConstructorMessageButtonUrl');
    var message_id = $('#telegramConstructorMessageButtonMessageId');
    $('.telegramConstructorMessageButtonTitle').val('');
    url.val('');
    message_id.val('').change();
}
$(document).on('click', '#modalFormAddButtonTgConstructor .addButton', function (e) {
    e.preventDefault();
    var url = $('#telegramConstructorMessageButtonUrl');
    var message_id = $('#telegramConstructorMessageButtonMessageId');
    addButton(url.val(), message_id.val());
    $('.telegramConstructorMessageButtonTitle').val('');
    button_form_clear();
});
$(document).on('click', '.tg-preview_message .fileinput-remove', function () {
    $(this).parent().parent().parent().parent().find('.is_delete_image').val(1);
});
var current_language = 'ru-RU';
if ($('.tabs_tg_message')) {
    $('.tabs_tg_message:first-of-type').addClass('active');
    $('.tg-preview_message:first-of-type').addClass('active');
    $(document).on('click', '.tabs_tg_message a', function (e) {
        e.preventDefault();
        $('.tabs_tg_message').removeClass('active');
        $('.tg-preview_message').removeClass('active');
        $(this).parent().addClass('active');
        current_language = $(this).attr('data-language');
        updateButtonsText();
        $($(this).attr('href')).addClass('active');
    });
}
var prevIndexButton = undefined;
function addButton(url, message_id) {
    if (!url && !message_id) {
        return;
    }
    if (prevIndexButton === undefined) {
        prevIndexButton = $('.telegram_message_buttons .telegram_message_buttons_item').length;
    }
    prevIndexButton++;
    var titlesArr = [];
    var titlesElements = $('.telegramConstructorMessageButtonTitle');
    for (var k = 0; k < titlesElements.length; k++) {
        titlesArr[titlesArr.length] = {text: titlesElements[k].value, language: titlesElements[k].dataset.language};
    }
    $.ajax({
        url: '/telegram-constructor-message/get-button',
        method: 'GET',
        data: {
            messageId: message_id,
            titles: JSON.stringify(titlesArr),
            languages: JSON.stringify(languages),
            url: url,
            index: prevIndexButton
        },
        success: function(button){
            if (button_updated === undefined) {
                // новая кнопка
                button = $($.parseHTML(button));
                $('.telegram_message_buttons').append(button);
                updateButtonsText();
            } else {
                // обновить кнопку
                button = $($.parseHTML(button));
                console.log(button);
                button_updated.html(button.html());
                button_updated = undefined;
                updateButtonsText();
            }
        }
    });
}

$( "#sortable-buttons" ).sortable({
    connectWith: ".connectedSortable",
}).disableSelection();

// Функция updateAudienceId удалена, используется новая логика в форме
if ($.fn.filseinputLocales) {
    $.fn.filseinputLocales['ru']['dropZoneTitle'] = "Выберите файлы";
}

function initBackend() {
    var colorPicker = $('.color_picker');
    var colorPickerText = $('.color_picker_text');
    if (colorPicker) {
        colorPicker.on('input', function (e) {
            var _el = $(this);
            var _colorPickerText = $(this).parent().parent().find('.color_picker_text');
            _colorPickerText.val(_el.val());
        });
        colorPickerText.on('input', function (e) {
            var _el = $(this);
            var _colorPicker = $(this).parent().parent().find('.color_picker');
            _colorPicker.val(_el.val());
        });
    }
}

initBackend();

// Мобильное меню sidebar: toggle на .sidebar-wrapper (off-canvas слева)
function initMobileSidebar() {
    const sidebarWrapper = $('.sidebar-wrapper');
    const overlay = $('#sidebar-overlay');
    const pushMenuBtn = $('#mobile-menu-toggle, [data-widget="pushmenu"]');
    const sidebarCollapseBtn = $('#sidebar-collapse-btn');
    const MOBILE_BREAKPOINT = 768;

    function isMobile() {
        return $(window).width() < MOBILE_BREAKPOINT;
    }

    function toggleSidebar() {
        sidebarWrapper.toggleClass('sidebar-open');
        overlay.toggleClass('active');
        $('body').toggleClass('sidebar-open');
    }

    function closeSidebar() {
        sidebarWrapper.removeClass('sidebar-open');
        overlay.removeClass('active');
        $('body').removeClass('sidebar-open');
    }

    pushMenuBtn.off('click.mobileSidebar').on('click.mobileSidebar', function(e) {
        e.preventDefault();
        if (isMobile()) {
            toggleSidebar();
        }
    });

    overlay.off('click.mobileSidebar').on('click.mobileSidebar', function() {
        closeSidebar();
    });

    // На мобиле кнопка «свернуть» в сайдбаре закрывает меню
    sidebarCollapseBtn.off('click.mobileSidebar').on('click.mobileSidebar', function() {
        if (isMobile()) {
            closeSidebar();
        }
    });

    // Закрытие при клике на ссылку в меню (не submenu toggle)
    sidebarWrapper.off('click.mobileSidebar', 'a.sidebar-menu-link').on('click.mobileSidebar', 'a.sidebar-menu-link', function() {
        if (!$(this).attr('data-toggle') && isMobile()) {
            setTimeout(closeSidebar, 200);
        }
    });

    $(window).off('resize.mobileSidebar').on('resize.mobileSidebar', function() {
        if ($(window).width() >= MOBILE_BREAKPOINT) {
            closeSidebar();
        }
    });
}

// Инициализация мобильного меню
initMobileSidebar();

$(document).on('pjax:send', function() {

});
$(document).on('pjax:complete', function() {
    initBackend();
    initMobileSidebar();
});