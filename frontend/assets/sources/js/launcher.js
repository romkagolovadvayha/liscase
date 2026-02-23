function initLauncher() {
    chat.send(JSON.stringify({'action': 'subscription', 'launcher': true}));
}
function launcherUpdate() {
    location.reload();
}
// «Получить» — только по клику на кнопку, не по всей карточке
$(document).on('click', '.store_launcher_cards_item_button', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var id = $(this).closest('.store_launcher_cards_item').attr('data-id');
    if (id) clickItem(id);
});

function clickItem(id) {
    $('.store_launcher_cards_item[data-id=' + id + ']').parent().addClass('loader');
    chat.send( JSON.stringify({'action' : 'getDrop', 'id': id}) );
}

function storeTake(response) {
    if (response.code === 200) {
        //toastr.success('<i class=\'fas fa-check-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    } else {
        $('.store_launcher_cards_item[data-id=' + response.id + ']').parent().removeClass('loader');
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    }
}

function storeAdd(html, id) {
    $('#products').prepend(html);
}

function storeGetItems(response) {
    if (response.code === 200) {
        $('.store_launcher_cards_item[data-id=' + response.id + ']').parent().remove();
        toastr.success('<i class=\'fas fa-check-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    } else {
        $('.store_launcher_cards_item[data-id=' + id + ']').parent().removeClass('loader');
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    }
}

// Обработка возврата товара
function storeReturnItem(response) {
    var $item = $('.store_launcher_cards_item[data-id=' + response.id + ']');
    var $button = $item.find('.store_launcher_cards_item_return');
    
    if (response.code === 200) {
        $item.parent().remove();
        toastr.success('<i class=\'fas fa-check-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    } else {
        $item.parent().removeClass('loader');
        $button.prop('disabled', false);
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    }
}

// Обработчик клика на кнопку возврата
$(document).on('click', '.store_launcher_cards_item_return', function(e) {
    e.stopPropagation();
    e.preventDefault();
    
    var id = $(this).attr('data-return-id');
    var $button = $(this);
    var $item = $('.store_launcher_cards_item[data-id=' + id + ']');
    
    if (!confirm('Вы уверены, что хотите вернуть этот товар?')) {
        return false;
    }
    
    $item.parent().addClass('loader');
    $button.prop('disabled', true);
    
    // Отправляем через websocket
    if (typeof chat !== 'undefined' && chat && chat.readyState === 1) {
        chat.send(JSON.stringify({'action': 'returnDrop', 'id': id}));
    } else {
        $item.parent().removeClass('loader');
        $button.prop('disabled', false);
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Ошибка соединения с сервером</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    }
    
    return false;
});