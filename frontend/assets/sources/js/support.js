let chatInited = false;

function initChat() {
    if (chatId === undefined || chatInited) return;
    chatInited = true;

    const $widget      = $('#widget_chat').addClass('active');
    const $wrap        = $('.support_messages_wrap');
    const $chat        = $('#chat');
    const $msg         = $('#supportMessage');
    const $sendBtn     = $('#supportMessageSend');
    const $fileInput   = $('#supportMessageFile');
    const $writed      = $('#supportChatWrited');
    const $progressBox = $('.support_messages_form_progress');
    const $fileBox     = $('.support_messages_form_file');

    // прогресс-кружок
    let cp = undefined;
    if ($progressBox.length) {
        cp = new CircleProgress('.support_messages_form_progress', { max: 100, value: 0, animationDuration: 400 });
    }

    // подписка в WS
    try {
        chat.send(JSON.stringify({ action: 'subscription', chat: chatId }));
    } catch(e){ /* noop */ }

    // утилиты
    const scrollToBottom = () => {
        // минимизируем измерения: используем scrollHeight контейнера
        $wrap[0].scrollTop = $wrap[0].scrollHeight;
    };

    const resizeMsg = (() => {
        let rafId = null;
        const MAX_H = 210;
        return () => {
            if (rafId) return;
            rafId = requestAnimationFrame(() => {
                $msg.css({ height: 'auto', padding: 0 });
                let h = $msg[0].scrollHeight;
                if (h > MAX_H) h = MAX_H;
                $msg.css({ height: h + 'px' });
                $wrap.css({ paddingBottom: (h + 60) + 'px' });
                scrollToBottom();
                rafId = null;
            });
        };
    })();

    const wsCanSend = () => (typeof chat !== 'undefined' && chat && chat.readyState === 1);

    const sendMessage = () => {
        const text = ($msg.val() || '').trim();
        if (!text || !wsCanSend()) return;
        try {
            chat.send(JSON.stringify({ action: 'chat', message: text, chatId }));
        } catch (e) { /* noop */ }
        $msg.val('');
        resizeMsg();
        // вернуть фокус мягко, без микрозадержек
        requestAnimationFrame(() => $msg.trigger('focus'));
    };

    // начальная прокрутка
    scrollToBottom();

    // события — предварительно чистим, чтобы не дублировать
    $(document).off('paste.support');
    $msg.off('keydown.support input.support focus.support blur.support');
    $sendBtn.off('click.support');
    $fileInput.off('change.support');
    $('.support_messages_header_close').off('click.support');

    // Enter для отправки (на самом textarea, не на всём document)
    $msg.on('keydown.support', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            sendMessage();
        }
    });

    // авто-resize по вводу
    $msg.on('input.support', resizeMsg);

    // кнопка «Отправить»
    $sendBtn.on('click.support', sendMessage);

    // paste -> загрузка файла (используем files, если есть)
    document.addEventListener('paste', function onPaste(event) {
        const files = event.clipboardData && event.clipboardData.files;
        if (files && files.length) {
            const file = files[0];
            const dt = new DataTransfer();
            dt.items.add(file);
            $fileInput[0].files = dt.files;
            sendFile(cp, { $progressBox, $fileBox });
        }
    }, { capture: false });

    // выбор файла
    $fileInput.on('change.support', () => sendFile(cp, { $progressBox, $fileBox }));

    // индикатор «печатает»
    $msg.on('focus.support', () => {
        if (wsCanSend()) chat.send(JSON.stringify({ action: 'chatFocus', chatId }));
    });
    $msg.on('blur.support', () => {
        if (wsCanSend()) chat.send(JSON.stringify({ action: 'chatBlur', chatId }));
    });

    // закрыть чат
    $('.support_messages_header_close').on('click.support', closeChat);
}

// ===== AJAX-хэлперы чата/тикетов =====
function supportChat(response) {
    const $wrap = $('.support_messages_wrap');
    const $chat = $('#chat');

    $.ajax({
        url: '/support/get-message',
        data: { id: response.messageId },
        cache: false
    }).done((res) => {
        if (!res) return;
        $chat.append(res);
        // один надёжный скролл вместо двух setTimeout
        $wrap[0].scrollTop = $wrap[0].scrollHeight;
        initTickets();
    });
}

function supportTicketsUpdate() {
    $.ajax({
        url: '/support/get-tickets',
        data: { id: chatId },
        cache: false
    }).done((res) => {
        if (!res) return;
        $('.tickets_wrap').html(res);
        initTickets();
    });
}

function supportChatFocus(response) {
    const $w = $('#supportChatWrited');
    $w.addClass('active').html(response.content);
}
function supportChatBlur() {
    $('#supportChatWrited').removeClass('active');
}

// ===== Upload =====
async function sendFile(cp, refs) {
    const { $progressBox = $('.support_messages_form_progress'), $fileBox = $('.support_messages_form_file') } = refs || {};
    const $fileInput = $('#supportMessageFile');
    const file = $fileInput[0].files[0];

    if (!file) {
        toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Пожалуйста, выберите файл для загрузки.</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
        return;
    }
    const sizeMb = file.size / 1_000_000;
    if (sizeMb > 2048) {
        toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Превышен максимальный обьем файла 2GB</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
        return;
    }

    $progressBox.show();
    $fileBox.hide();

    $.ajax({
        url: '/support/create-presigned-request',
        data: { fileName: file.name, s: file.size },
        cache: false
    }).done((res) => {
        if (!res) return;
        if (res.code === 200) {
            uploadFile(file, res.url, cp, res.filename, $progressBox, $fileBox);
        } else {
            $progressBox.hide(); $fileBox.show();
            toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>" + res.message + "</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
        }
    });
}

function uploadFile(file, url, cp, newFilename, $progressBox, $fileBox) {
    const xhr = new XMLHttpRequest();
    xhr.open('PUT', url, true);

    // throttle прогресса до rAF
    let lastLoaded = 0, lastTotal = 0, rafPending = false;
    xhr.upload.onprogress = (pe) => {
        if (!pe.lengthComputable || !cp) return;
        lastLoaded = pe.loaded; lastTotal = pe.total;
        if (rafPending) return;
        rafPending = true;
        requestAnimationFrame(() => {
            const percent = (100 * lastLoaded) / lastTotal;
            cp.value = percent;
            cp.el.style.setProperty('--progress-value', percent / 100);
            rafPending = false;
        });
    };

    xhr.onload = function () {
        if (xhr.status === 200) {
            successFile(file.name, file.type, cp, newFilename, $progressBox, $fileBox);
        } else {
            $progressBox.hide(); $fileBox.show();
            toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Ошибка загрузки файла</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
        }
    };
    xhr.onerror = function () {
        $progressBox.hide(); $fileBox.show();
        toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Ошибка запроса</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
    };

    xhr.send(file);
}

function successFile(filename, mimetype, cp, newFilename, $progressBox, $fileBox) {
    $.ajax({
        url: '/support/upload-file-save',
        method: 'POST',
        data: { mimetype, newFilename, fileName: filename, id: chatId },
        cache: false
    }).done((res) => {
        $progressBox.hide(); $fileBox.show();
        if (cp) { cp.value = 0; cp.el.style.setProperty('--progress-value', 0); }
        if (!res) return;
        if (res.code !== 200) {
            toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>" + res.message + "</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
        } else if (res.redirect) {
            location.href = res.redirect;
        }
    });
}

// ===== Таймеры тикетов =====
function initTickets() {
    const $timers = $('.ticket_timer');
    if (!$timers.length) return;
    for (let i = 0; i < $timers.length; i++) {
        const el = $timers[i];
        const dateTime = el.getAttribute('data-time');
        if (!dateTime) continue;
        const left = moment.unix(dateTime);
        el.innerHTML = left.locale(lang).fromNow();
    }
}

initTickets();