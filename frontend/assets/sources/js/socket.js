var chat;
var reconnectTimeout = null;
var reconnectAttempts = 0;
var maxReconnectAttempts = 5;
var reconnectDelay = 5000;
var isConnecting = false;

function connectWs() {
    // Защита от множественных подключений
    if (isConnecting) {
        console.log('WebSocket: Уже идет подключение, пропускаем...');
        return;
    }
    
    // Проверяем, не подключены ли мы уже
    if (chat && (chat.readyState === WebSocket.OPEN || chat.readyState === WebSocket.CONNECTING)) {
        console.log('WebSocket: Уже подключен или идет подключение, пропускаем...', { readyState: chat.readyState });
        return;
    }
    
    // Закрываем предыдущее соединение, если оно есть
    if (chat && chat.readyState !== WebSocket.CLOSED) {
        try {
            chat.close();
        } catch (err) {
            console.warn('WebSocket: Ошибка при закрытии предыдущего соединения:', err);
        }
    }
    
    isConnecting = true;
    chat = new WebSocket(ws);

    chat.onmessage = function(e) {
        // Обработка WS-level ping фреймов (бинарные данные)
        if (typeof e.data === 'string' && e.data.length === 0) {
            // Это может быть WS-level ping фрейм, браузер автоматически ответит pong
            return;
        }
        
        var response;
        try {
            response = JSON.parse(e.data);
        } catch (err) {
            console.error('Ошибка разбора JSON:', err, e.data);
            return;
        }
        
        if (response.type === 'ping' || response.action === 'ping') {
            // Ответ app-level pong (используем строчные буквы, как ожидает сервер)
            try {
                chat.send(JSON.stringify({ action: 'pong', type: 'pong', ts: response.ts }));
            } catch (err) {
                console.error('WebSocket: Ошибка отправки pong:', err);
            }
            return;
        }
        if (response.type && response.type === 'chat') {
            supportChat(response);
        }
        if (response.type && response.type === 'chatFocus') {
            supportChatFocus(response);
        }
        if (response.type && response.type === 'chatBlur') {
            supportChatBlur(response);
        }
        if (response.type && response.type === 'ticketsUpdate') {
            supportTicketsUpdate(response);
        }
        if (response.type && response.type === 'launcherUpdate') {
            launcherUpdate(response);
        }
        if (response.type && response.type === 'support_notifications') {
            supportNotification(response);
        }
        if (response.type && response.type === 'error') {
            toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + response.error + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
            // Если есть tempId в ответе, обновляем статус соответствующего сообщения
            if (response.tempId && typeof updateMessageStatus === 'function') {
                updateMessageStatus(response.tempId, 'error');
            }
        }
        if (response.type && response.type === 'redirect') {
            location.href = response.url;
        }
        if ($('.store_launcher')) {
            if (response.type && response.type === 'store.take') {
                storeTake(response);
            }
            if (response.type && response.type === 'store.buy.items') {
                storeAdd(response.product, response.id);
            }
            if (response.type && response.type === 'store.get.items') {
                storeGetItems(response);
            }
            if (response.type && response.type === 'store.return.item') {
                if (typeof storeReturnItem === 'function') {
                    storeReturnItem(response);
                }
            }
        }
        if ($('.balance_count').length) {
            if (response.type && response.type === 'update.balance') {
                updateBalance(response);
                moneyNotification(response);
            }
        }
        if ($('.players_js')) {
            if (response.type && response.type === 'update.online') {
                updateOnline(response);
            }
        }
    };
    chat.onerror = function(err) {
        console.error('WebSocket ошибка:', err);
        // При ошибке WebSocket обновляем статус всех pending сообщений на ошибку
        if (typeof pendingMessages !== 'undefined' && typeof updateMessageStatus === 'function') {
            Object.keys(pendingMessages).forEach(tempId => {
                updateMessageStatus(tempId, 'error');
            });
        }
    };

    chat.onclose = function(e) {
        isConnecting = false;
        console.warn('WebSocket соединение закрыто. Причина:', e.reason || e.code, 'Код:', e.code, 'Было чистое закрытие:', e.wasClean);
        
        // Переподключаемся только если:
        // 1. Это не было нормальное закрытие (код 1000) ИЛИ это был таймаут (код 1008)
        // 2. Не превышен лимит попыток
        var shouldReconnect = (!e.wasClean || e.code === 1008 || e.code === 1000) && reconnectAttempts < maxReconnectAttempts;
        
        if (shouldReconnect) {
            reconnectAttempts++;
            console.log('WebSocket: Попытка переподключения (' + reconnectAttempts + '/' + maxReconnectAttempts + ')...');
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
            }
            reconnectTimeout = setTimeout(function() {
                reconnectWebSocket();
            }, reconnectDelay);
        } else if (reconnectAttempts >= maxReconnectAttempts) {
            console.error('WebSocket: Достигнут лимит попыток переподключения');
        } else {
            console.log('WebSocket: Переподключение не требуется');
        }
    };

    chat.onopen = function(e) {
        isConnecting = false;
        reconnectAttempts = 0; // Сбрасываем счетчик при успешном подключении
        console.log('WebSocket: Соединение установлено');
        
        if (token !== undefined) {
            var item = {'action' : 'auth', 'token' : token, 'steam_id' : steam_id};
            item.launcher = $('.store_launcher').length > 0;
            try {
                chat.send(JSON.stringify(item));
                console.log('WebSocket: Отправлен запрос авторизации');
            } catch (err) {
                console.error('WebSocket: Ошибка отправки авторизации:', err);
            }
            if ($('#supportMessage').length) {
                initChat();
            }
        }
    };
}

// Инициализация подключения
if (typeof ws !== 'undefined' && ws) {
    connectWs();
} else {
    console.error('WebSocket: URL не определен');
}

function reconnectWebSocket() {
    if (reconnectTimeout) {
        clearTimeout(reconnectTimeout);
        reconnectTimeout = null;
    }
    console.log('WebSocket: Попытка переподключения...');
    connectWs();
}
function supportNotification(response) {
    var notification = $('.menu__item_support .main-menu-notification');
    sound('/sound/notification.mp3', response.hash);
    if (response.count <= 0) {
        if (notification.length) {
            notification.hide();
        }
        return;
    }
    if (chatId !== undefined && chatId === response.chatId) {
        return;
    }
    if (notification.length) {
        notification.html(response.count);
        return;
    }
    var support_item = $('.menu__item_support a');
    support_item.append('<span class="main-menu-notification ">' + response.count + '</span>');
}
function moneyNotification(response) {
    sound('/sound/money.mp3', response.hash);
}

var notificationSound = undefined;
function sound(file, hash) {
    notificationSound = new Audio(file);
    notificationSound.volume = 0.2;  // Устанавливаем громкость от 0 до 1
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

function openChat(href) {
    var chatBody = $('#widget_chat .widget_chat_body');
    chatBody.load(href, function () {
        initChat();
    });
}

function closeChat() {
    $('#widget_chat').removeClass('active');
}

function updateOnline(response) {
    $('.online_counter').html(response.total);
    for (var i = 0; i < response.servers.length; i++) {
        var item = response.servers[i];
        var serverItem = $('.server_item_js[data-server-id=' + item.server_id + ']');
        serverItem.removeClass('server_status0');
        serverItem.removeClass('server_status1');
        serverItem.removeClass('server_status2');
        serverItem.addClass('server_status' + item.status);
        serverItem.find('.players_js').html(item.players + item.joined);
        serverItem.find('.progress_js').animate({ width: (item.percentPlayers + item.percentJoined) + "%" });
        serverItem.find('.players_progress_js').animate({ width: (item.percentPlayersAbsolute + item.percentJoinedAbsolute) + "%" });
        serverItem.find('.queued_progress_js').animate({ width: (item.percentQueuedAbsolute) + "%" });
    }
}