var chat;
function connectWs() {
    chat = new WebSocket(ws);

    chat.onmessage = function(e) {
        var response;
        try {
            response = JSON.parse(e.data);
        } catch (err) {
            console.error('Ошибка разбора JSON:', err, e.data);
            return;
        }
        if (response.type === 'ping') {
            // ответ app-level pong (ВАЖНО: используем ваш протокол команд)
            chat.send(JSON.stringify({ action: 'Pong', ts: response.ts }));
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
    };

    chat.onclose = function(e) {
        console.warn('WebSocket соединение закрыто. Причина:', e.reason || e.code);
        // Попробовать переподключиться через 2 секунд
        setTimeout(() => reconnectWebSocket(), 5000);
    };

    chat.onopen = function(e) {
        if (token !== undefined) {
            var item = {'action' : 'auth', 'token' : token, 'steam_id' : steam_id};
            item.launcher = $('.store_launcher').length > 0;
            chat.send(JSON.stringify(item));
            if ($('#supportMessage').length) {
                initChat();
            }
        }
    };
}
connectWs();
function reconnectWebSocket() {
    console.log('Попытка переподключения...');
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