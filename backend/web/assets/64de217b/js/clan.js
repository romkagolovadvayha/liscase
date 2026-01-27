$('#EDIT_BACKGROUND').on('change', function () {
    sendFileClan('EDIT_BACKGROUND', '/clans/upload?hash=' + CLAN_HASH + '&type=background', $('.profile_clan'));
});
$('#EDIT_LOGO').on('change', function () {
    sendFileClan('EDIT_LOGO', '/clans/upload?hash=' + CLAN_HASH +'&type=logo', $('.profile_clan .profile_clan__logo'));
});
function sendFileClan(id, url, imageEl) {
    var file = document.getElementById(id).files[0];
    if (!file) {
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Пожалуйста, выберите файл для загрузки.</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
        return;
    }
    var s = file.size / 1000000;
    if (s > 3) {
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Превышен максимальный обьем файла 3MB</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
        return;
    }
    $('.profile_clan').addClass('kv-grid-loading');
    uploadFileClan(file, url, imageEl);
}
function uploadFileClan(file, url, imageEl) {
    // Создаем новый XMLHttpRequest для загрузки
    var xhr = new XMLHttpRequest();
    xhr.open('PUT', url, true);
    xhr.setRequestHeader('X-File-Name', file.name);

    // Обработчик завершения загрузки
    xhr.onload = function() {
        $('.profile_clan').removeClass('kv-grid-loading');
        if (xhr.status === 200) {
            imageEl.css('background-image', 'url(' + xhr.response + ')');
            //successFile(file.name, file.type);
        } else {
            toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + xhr.response + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
        }
    };

    // Обработчик ошибки запроса
    xhr.onerror = function() {
        $('.profile_clan').removeClass('kv-grid-loading');
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Ошибка запроса</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    };

    xhr.send(file);
}