let chatInited = false;
let pendingMessages = {}; // Хранилище временных сообщений: { tempId: { $element, text, timestamp } }
window.pendingMessages = pendingMessages; // Делаем доступным глобально

// === Настройки скролла ===
const BOTTOM_OFFSET = 60; // отступ от низа в пикселях

function scrollBottomOffset(offset = BOTTOM_OFFSET) {
    const el = document.querySelector('.support_messages_wrap');
    if (!el) return;
    el.scrollTop = Math.max(0, el.scrollHeight - el.clientHeight + offset);
}

function forceScrollToBottom(offset = BOTTOM_OFFSET) {
    const $wrap = $('.support_messages_wrap');
    if ($wrap.length) {
        // мгновенный скролл без smooth и без "якорения"
        $wrap.css({ 'scroll-behavior': 'auto', 'overflow-anchor': 'none' });
    }

    const go = () => scrollBottomOffset(offset);

    go();                                  // сразу
    requestAnimationFrame(go);             // после 1-го layout
    requestAnimationFrame(() => requestAnimationFrame(go)); // ещё один кадр

    // когда загрузятся веб-шрифты
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(go);
    }
    // когда догрузятся картинки внутри чата
    $('#chat img').each(function () {
        if (!this.complete) {
            this.addEventListener('load', go, { once: true });
            this.addEventListener('error', go, { once: true });
        }
    });
}

function nearBottom(el, px = 40, offset = BOTTOM_OFFSET) {
    if (!el) return true;
    const rest = el.scrollHeight - el.clientHeight - el.scrollTop + offset;
    return rest <= px;
}

// ===== Хелпер для форматирования времени =====
function formatTimeFromNow(timestamp, locale = 'ru') {
    if (typeof moment !== 'undefined') {
        return moment.unix(timestamp).locale(locale).fromNow();
    }
    // Альтернативный способ без moment
    const now = Math.floor(Date.now() / 1000);
    const diff = now - timestamp;
    
    if (diff < 60) return 'только что';
    if (diff < 3600) {
        const minutes = Math.floor(diff / 60);
        return minutes + ' ' + (minutes === 1 ? 'минуту' : minutes < 5 ? 'минуты' : 'минут') + ' назад';
    }
    if (diff < 86400) {
        const hours = Math.floor(diff / 3600);
        return hours + ' ' + (hours === 1 ? 'час' : hours < 5 ? 'часа' : 'часов') + ' назад';
    }
    if (diff < 604800) {
        const days = Math.floor(diff / 86400);
        return days + ' ' + (days === 1 ? 'день' : days < 5 ? 'дня' : 'дней') + ' назад';
    }
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString(locale === 'ru' ? 'ru-RU' : 'en-US', {
        day: 'numeric',
        month: 'short',
        year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
    });
}

// Глобальная функция для обновления статуса сообщения
function updateMessageStatus(tempId, status, messageId = null) {
    if (!pendingMessages[tempId]) return;
    
    const { $element, timeoutId } = pendingMessages[tempId];
    
    // Очищаем таймаут, если он был установлен
    if (timeoutId) {
        clearTimeout(timeoutId);
    }
    
    const $statusElement = $element.find('.message-status');
    
    $statusElement.removeClass('message-status-sending message-status-error message-status-success');
    
    if (status === 'success') {
        // Если пришел messageId, обновляем data-temp-id на реальный ID
        if (messageId) {
            $element.attr('data-message-id', messageId);
            $element.removeAttr('data-temp-id');
        }
        // Просто удаляем статус без показа галочки
        $statusElement.fadeOut(200, function() {
            $(this).remove();
        });
        // Удаляем из pending
        delete pendingMessages[tempId];
    } else if (status === 'error') {
        $statusElement.addClass('message-status-error');
        $statusElement.html('<i class="fas fa-exclamation-triangle"></i><span class="message-status-text">Ошибка отправки</span>');
        // Добавляем обработчик клика для повторной отправки
        $statusElement.off('click.retry').on('click.retry', function() {
            retryMessage(tempId);
        });
    }
}

// Делаем функцию доступной глобально
window.updateMessageStatus = updateMessageStatus;

// Глобальная функция для повторной отправки сообщения
function retryMessage(tempId) {
    if (!pendingMessages[tempId]) return;
    
    const { text, $element } = pendingMessages[tempId];
    const $statusElement = $element.find('.message-status');
    
        // Обновляем статус на "отправляется"
        $statusElement.removeClass('message-status-error').addClass('message-status-sending');
        $statusElement.html('<i class="fas fa-clock"></i><span class="message-status-text">Отправляется...</span>');
    
    const wsCanSend = () => (typeof chat !== 'undefined' && chat && chat.readyState === 1);
    
    if (!wsCanSend()) {
        updateMessageStatus(tempId, 'error');
        toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Нет соединения с сервером</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
        return;
    }
    
    try {
        chat.send(JSON.stringify({ action: 'chat', message: text, chatId, tempId: tempId }));
    } catch (e) {
        updateMessageStatus(tempId, 'error');
        toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Произошла ошибка, попробуйте еще раз.</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
    }
}

function initChat() {
    if (typeof chatId === 'undefined' || chatInited) return;
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

    // авто-резайз инпута + доскролл с учётом оффсета
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
                scrollBottomOffset();
                rafId = null;
            });
        };
    })();

    const wsCanSend = () => (typeof chat !== 'undefined' && chat && chat.readyState === 1);

    // Функция для создания временного сообщения
    const createPendingMessage = (text) => {
        const tempId = 'temp_' + Date.now();
        const timestamp = Math.floor(Date.now() / 1000);
        
        // Получаем данные пользователя из существующих сообщений
        let avatar = '/images/default-avatar.png';
        
        // Ищем аватар из сообщений текущего пользователя (сообщения без username - это наши)
        const $ourMessages = $chat.find('.support_messages_item').filter(function() {
            return $(this).find('.support_messages_item_message_username').length === 0;
        });
        
        if ($ourMessages.length > 0) {
            const $avatar = $ourMessages.first().find('.support_messages_item_profile_avatar img');
            if ($avatar.length) {
                avatar = $avatar.attr('src') || avatar;
            }
        } else {
            // Если не нашли, ищем любой аватар из сообщений
            const $anyMessage = $chat.find('.support_messages_item').first();
            if ($anyMessage.length) {
                const $anyAvatar = $anyMessage.find('.support_messages_item_profile_avatar img');
                if ($anyAvatar.length) {
                    avatar = $anyAvatar.attr('src') || avatar;
                }
            }
        }
        
        // Экранируем текст для безопасности
        const escapedText = $('<div>').text(text).html().replace(/\n/g, '<br>');
        
        // Создаем HTML временного сообщения
        const messageHtml = `
            <div class="support_messages_item pending-message" data-temp-id="${tempId}">
                <div class="support_messages_item_profile">
                    <div class="support_messages_item_profile_avatar"><img src="${avatar}"></div>
                </div>
                <div class="support_messages_item_message">
                    <div class="support_messages_item_message_content">
                        <div class="support_messages_item_message_text" style="white-space: pre-line;">${escapedText}</div>
                    </div>
                </div>
                <div class="support_messages_item_date_wrapper">
                    <div class="message-status message-status-sending">
                        <i class="fas fa-clock"></i>
                        <span class="message-status-text">Отправляется...</span>
                    </div>
                    <div class="support_messages_item_date ticket_timer" data-time="${timestamp}">
                        ${formatTimeFromNow(timestamp, typeof lang !== 'undefined' ? lang : 'ru')}
                    </div>
                </div>
            </div>
        `;
        
        const $messageElement = $(messageHtml);
        $chat.append($messageElement);
        
        // Сохраняем информацию о временном сообщении
        pendingMessages[tempId] = {
            $element: $messageElement,
            text: text,
            timestamp: timestamp,
            timeoutId: null
        };
        
        // Устанавливаем таймаут: если через 10 секунд не пришел ответ, помечаем как ошибку
        pendingMessages[tempId].timeoutId = setTimeout(() => {
            if (pendingMessages[tempId]) {
                updateMessageStatus(tempId, 'error');
            }
        }, 10000);
        
        // Прокручиваем вниз
        forceScrollToBottom();
        
        return tempId;
    };


    const sendMessage = () => {
        const text = ($msg.val() || '').trim();
        if (!text) return;
        
        if (!wsCanSend()) {
            toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Нет соединения с сервером</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
            return;
        }
        
        // Создаем временное сообщение сразу
        const tempId = createPendingMessage(text);
        
        try {
            chat.send(JSON.stringify({ action: 'chat', message: text, chatId, tempId: tempId }));
            $msg.val('');
        } catch (e) {
            // При ошибке отправки обновляем статус на ошибку
            updateMessageStatus(tempId, 'error');
            toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>Произошла ошибка, попробуйте еще раз.</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
            return;
        }
        resizeMsg();
        requestAnimationFrame(() => $msg.trigger('focus'));
    };

    // начальная прокрутка — сразу и надёжно (вниз - 60px)
    forceScrollToBottom();

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

    // paste -> загрузка файла (без утечки слушателей)
    $(document).on('paste.support', function (event) {
        const files = event.originalEvent?.clipboardData?.files;
        if (files && files.length) {
            const file = files[0];
            const dt = new DataTransfer();
            dt.items.add(file);
            $fileInput[0].files = dt.files;
            sendFile(cp, { $progressBox, $fileBox });
        }
    });

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

    // авто-доскролл при добавлении новых сообщений — только если пользователь "почти внизу" (с учётом оффсета)
    const mo = new MutationObserver(() => {
        const el = $wrap[0];
        if (!el) return;
        if (nearBottom(el)) scrollBottomOffset();
    });
    if ($chat[0]) mo.observe($chat[0], { childList: true });
}

// ===== AJAX-хэлперы чата/тикетов =====
function supportChat(response) {
    const $chat = $('#chat');
    
        // Проверяем, есть ли временное сообщение с таким tempId
    if (response.tempId && pendingMessages[response.tempId]) {
        // Обновляем статус временного сообщения на успешный
        updateMessageStatus(response.tempId, 'success', response.messageId);
        
        // Заменяем временное сообщение на реальное
        $.ajax({
            url: '/support/get-message',
            data: { id: response.messageId },
            cache: false
        }).done((res) => {
            if (!res) return;
            const $pendingElement = pendingMessages[response.tempId].$element;
            const $newMessage = $(res);
            $pendingElement.replaceWith($newMessage);
            // жёсткий скролл — мгновенно к «вниз − 60px»
            forceScrollToBottom();
            initTickets();
            // Удаляем из pending
            delete pendingMessages[response.tempId];
        });
    } else {
        // Обычное сообщение (не наше или tempId не пришел)
        // Если есть pending сообщения, проверяем, может это ответ на одно из них
        // (если сервер не вернул tempId, но мы можем сопоставить по времени)
        let foundPending = false;
        if (Object.keys(pendingMessages).length > 0) {
            // Берем самое последнее pending сообщение
            const tempIds = Object.keys(pendingMessages).sort().reverse();
            const lastTempId = tempIds[0];
            if (pendingMessages[lastTempId]) {
                // Обновляем статус последнего pending сообщения
                updateMessageStatus(lastTempId, 'success', response.messageId);
                // Заменяем на реальное
                $.ajax({
                    url: '/support/get-message',
                    data: { id: response.messageId },
                    cache: false
                }).done((res) => {
                    if (!res) return;
                    const $pendingElement = pendingMessages[lastTempId].$element;
                    const $newMessage = $(res);
                    $pendingElement.replaceWith($newMessage);
                    forceScrollToBottom();
                    initTickets();
                    delete pendingMessages[lastTempId];
                });
                foundPending = true;
            }
        }
        
        if (!foundPending) {
            // Обычное сообщение (не наше)
            $.ajax({
                url: '/support/get-message',
                data: { id: response.messageId },
                cache: false
            }).done((res) => {
                if (!res) return;
                $chat.append(res);
                // жёсткий скролл — мгновенно к «вниз − 60px»
                forceScrollToBottom();
                initTickets();
            });
        }
    }
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

// ===== Таймеры тикетов =====
function initTickets() {
    const $timers = $('.ticket_timer');
    if (!$timers.length) return;
    for (let i = 0; i < $timers.length; i++) {
        const el = $timers[i];
        const dateTime = el.getAttribute('data-time');
        if (!dateTime) continue;
        const currentLang = typeof lang !== 'undefined' ? lang : 'ru';
        el.innerHTML = formatTimeFromNow(parseInt(dateTime), currentLang);
    }
}

// Инициализируем таймеры после загрузки DOM и moment (если доступен)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Ждем загрузки moment или используем альтернативный способ
        if (typeof moment !== 'undefined') {
            initTickets();
        } else {
            // Пробуем подождать момент или используем альтернативный способ
            setTimeout(function() {
                initTickets();
            }, 100);
        }
    });
} else {
    // DOM уже загружен
    if (typeof moment !== 'undefined') {
        initTickets();
    } else {
        setTimeout(function() {
            initTickets();
        }, 100);
    }
}

// ===== Просмотр изображений в полноэкранном режиме =====
$(document).on('click', '.support-image-viewer', function(e) {
    e.preventDefault();
    
    const $link = $(this);
    const imageUrl = $link.attr('data-image-url') || $link.attr('href');
    const imageTitle = $link.attr('data-image-title') || $link.attr('title') || '';
    
    // Создаем модальное окно, если его еще нет
    let $modal = $('#support-image-modal');
    if (!$modal.length) {
        $modal = $('<div id="support-image-modal" class="support-image-modal">' +
            '<div class="support-image-modal-overlay"></div>' +
            '<div class="support-image-modal-content">' +
            '<button class="support-image-modal-close" aria-label="Закрыть">&times;</button>' +
            '<div class="support-image-modal-image-wrapper">' +
            '<img class="support-image-modal-image" src="" alt=""/>' +
            '</div>' +
            '<div class="support-image-modal-title"></div>' +
            '</div>' +
            '</div>');
        $('body').append($modal);
        
        // Обработчик закрытия по клику на overlay или кнопку закрытия
        $modal.find('.support-image-modal-overlay, .support-image-modal-close').on('click', function() {
            $modal.removeClass('active');
            setTimeout(function() {
                $modal.remove();
            }, 300);
        });
        
        // Закрытие по Escape
        $(document).on('keydown.support-image', function(e) {
            if (e.key === 'Escape' && $modal.hasClass('active')) {
                $modal.removeClass('active');
                setTimeout(function() {
                    $modal.remove();
                }, 300);
                $(document).off('keydown.support-image');
            }
        });
    }
    
    // Устанавливаем изображение и заголовок
    $modal.find('.support-image-modal-image').attr('src', imageUrl).attr('alt', imageTitle);
    $modal.find('.support-image-modal-title').text(imageTitle);
    
    // Показываем модальное окно
    $modal.addClass('active');
});
