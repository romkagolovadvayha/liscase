$('.store_launcher_cards_item').on('click', function () {
    var id = $(this).attr('data-id');
    chat.send( JSON.stringify({'action' : 'getDrop', 'id': id}) );
});

function storeTake(response) {
    if (response.code === 200) {
        $('.store_launcher_cards_item[data-id=' + response.id + ']').parent().hide();
        toastr.success('<i class=\'fas fa-check-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    } else {
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    }
}