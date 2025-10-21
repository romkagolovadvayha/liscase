function initLauncher() {
    chat.send(JSON.stringify({'action': 'subscription', 'launcher': true}));
}
function launcherUpdate() {
    location.reload();
}
$('.store_launcher_cards_item').on('click', function () {
    clickItem($(this).attr('data-id'));
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
    $('.store_launcher_cards_item[data-id=' + id + ']').on('click', function () {
        clickItem($(this).attr('data-id'));
    });
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