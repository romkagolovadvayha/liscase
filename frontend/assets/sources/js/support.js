function initChat() {
    if (chatId === undefined) {
        return;
    }
    $('#widget_chat').addClass('active');
    chat.send(JSON.stringify({'action': 'subscription', 'chat': chatId}));
    var support_messages_wrap = $('.support_messages_wrap');
    support_messages_wrap.scrollTop($('#chat').height());
    var supportMessage = $('#supportMessage');
    var cp = undefined;
    if ($('.support_messages_form_progress').length) {
        cp = new CircleProgress('.support_messages_form_progress', {
            max: 100,
            value: 0,
            animationDuration: 400,
            // textFormat: (val) => val + '°',
        });
    }
    $(document).keydown(function(e) {
        if (e.keyCode === 13 && !e.shiftKey) {
            if (supportMessage.val()) {
                chat.send( JSON.stringify({'action' : 'chat', 'message' : supportMessage.val(), 'chatId': chatId}) );
                supportMessage.val('');
                supportMessage.blur();
                supportMessage.trigger('keydown');
                setTimeout(function() {
                    supportMessage.focus();
                }, 10);
            }
        }
    });
    $('#supportMessageSend').on('click', function () {
        if (supportMessage.val()) {
            chat.send( JSON.stringify({'action' : 'chat', 'message' : supportMessage.val(), 'chatId': chatId}) );
            supportMessage.val('');
            supportMessage.blur();
            supportMessage.trigger('keydown');
            setTimeout(function() {
                supportMessage.focus();
            }, 10);
        }
    });
    supportMessage.on('keydown', function () {
        setTimeout(function() {
            supportMessage.css('height', 'auto');
            supportMessage.css('padding', '0');
            var height = document.getElementById("supportMessage").scrollHeight;
            if (height > 210) {
                height = 210;
            }
            supportMessage.css('height', height + 'px');
            support_messages_wrap.css('paddingBottom', (height + 60) + 'px');
            support_messages_wrap.scrollTop($('#chat').height());
        }, 1);
    });

    document.addEventListener("paste", function(event) {
        // Проверяем, есть ли файл в буфере обмена
        const clipboardItems = event.clipboardData.items;
        for (let i = 0; i < clipboardItems.length; i++) {
            const item = clipboardItems[i];
            if (item.kind === "file") {
                const file = item.getAsFile();
                const fileInput = document.getElementById("supportMessageFile");
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                sendFile(cp);
                break;
            }
        }
    });
    $('#supportMessageFile').on('change', function () {
        sendFile(cp);
    });
    $('#supportMessage').on('focus', function () {
        chat.send( JSON.stringify({'action' : 'chatFocus', 'chatId': chatId}) );
    });
    $('#supportMessage').on('blur', function () {
        chat.send( JSON.stringify({'action' : 'chatBlur', 'chatId': chatId}) );
    });
    $('.support_messages_header_close').on('click', function () {
        closeChat();
    });
}

function supportChat(response) {
    $.ajax({
        url: '/support/get-message?id=' + response.messageId,
        success: function (res) {
            if (res) {
                $('#chat').append(res);
                $('.support_messages_wrap').scrollTop($('#chat').height());
                setTimeout(function () {
                    $('.support_messages_wrap').scrollTop($('#chat').height());
                }, 400);
                initTickets();
            }
        }
    });
}
function supportTicketsUpdate(response) {
    $.ajax({
        url: '/support/get-tickets?id=' + chatId,
        success: function (res) {
            if (res) {
                $('.tickets_wrap').html(res);
                initTickets();
            }
        }
    });
}
function supportChatFocus(response) {
    $('#supportChatWrited').addClass('active');
    $('#supportChatWrited').html(response.content);
}
function supportChatBlur(response) {
    $('#supportChatWrited').removeClass('active');
}
async function sendFile(cp) {
    var file = document.getElementById('supportMessageFile').files[0];
    if (!file) {
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Пожалуйста, выберите файл для загрузки.</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
        return;
    }
    var s = file.size / 1000000;
    if (s > 1024 * 2) {
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Превышен максимальный обьем файла 2GB</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
        return;
    }
    $('.support_messages_form_progress').show();
    $('.support_messages_form_file').hide();
    $.ajax({
        url: '/support/create-presigned-request?fileName=' + file.name + '&s=' + file.size,
        success: function (res) {
            if (res) {
                if (res.code === 200) {
                    uploadFile(file, res.url, cp, res.filename);
                } else {
                    $('.support_messages_form_progress').hide();
                    $('.support_messages_form_file').show();
                    toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + res.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
                }
            }
        }
    });
}
function uploadFile(file, url, cp, newFilename) {
    // Создаем новый XMLHttpRequest для загрузки
    var xhr = new XMLHttpRequest();
    xhr.open('PUT', url, true);

    // Обработчик события прогресса
    xhr.upload.onprogress = function(pe) {
        if (pe.lengthComputable) {
            var percent = (100 / pe.total) * pe.loaded;
            cp.value = percent;
            cp.el.style.setProperty('--progress-value', percent / 100);
            // Обновляем прогресс-бар (если он есть)
            // progressBar.max = pe.total;
            // progressBar.value = pe.loaded;
        }
    };

    // Обработчик завершения загрузки
    xhr.onload = function() {
        if (xhr.status === 200) {
            successFile(file.name, file.type, cp, newFilename);
        } else {
            $('.support_messages_form_progress').hide();
            $('.support_messages_form_file').show();
            toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Ошибка загрузки файла</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
        }
    };

    // Обработчик ошибки запроса
    xhr.onerror = function() {
        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Ошибка запроса</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
    };

    xhr.send(file);
}
function successFile(filename, mimetype, cp, newFilename) {
    $.ajax({
        url: '/support/upload-file-save?mimetype=' + mimetype + '&newFilename=' + newFilename + '&fileName=' + filename + '&id=' + chatId,
        method: 'post',
        success: function (res) {
            if (res) {
                $('.support_messages_form_progress').hide();
                $('.support_messages_form_file').show();
                cp.value = 0;
                cp.el.style.setProperty('--progress-value', 0);
                if (res.code !== 200) {
                    toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + res.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
                } else if (res.redirect) {
                    location.href = res.redirect;
                }
            }
        }
    });
}
function initTickets() {
    var timers = $('.ticket_timer');
    for (var i = 0; i < timers.length; i++) {
        var dateTime = $(timers[i]).attr('data-time');
        var left = moment.unix(dateTime);
        $(timers[i]).html(left.locale(lang).fromNow());
    }
}
initTickets();


// document.querySelector('#value-input').addEventListener('change', e => {
//     const val = e.target.value;
//     cp.value = val;
//     cp.el.style.setProperty('--progress-value', val / MAX);
// })