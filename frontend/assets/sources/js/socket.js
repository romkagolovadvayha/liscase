var chat = new WebSocket(ws);

chat.onmessage = function(e) {
    var response = JSON.parse(e.data);
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
        if (response.type && response.type === 'store.get.items') {
            storeGetItems(response);
        }
    }
    if ($('.balance_count')) {
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

chat.onopen = function(e) {
    if (token !== undefined) {
        chat.send( JSON.stringify({'action' : 'auth', 'token' : token, 'steam_id' : steam_id}) );
        if ($('#supportMessage')) {
            initChat();
        }
    }
};

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