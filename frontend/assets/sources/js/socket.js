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
        }
    }
    if ($('.players_js')) {
        if (response.type && response.type === 'update.online') {
            updateOnline(response);
        }
    }
};

chat.onopen = function(e) {
    if (token !== undefined) {
        chat.send( JSON.stringify({'action' : 'auth', 'token' : token, 'steam_id' : steam_id}) );
    }
};

$(document).ready(function () {
    $(document).on('click', '.show-chat-js', function (e) {
        e.preventDefault();
        var href = $(this).data('href');
        if (!href) {
            href = $(this).attr('href');
        }
        openChat(href);
        return false;
    });
});

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