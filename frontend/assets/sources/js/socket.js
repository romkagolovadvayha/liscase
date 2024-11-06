var chat = new WebSocket('ws://localhost:4888');

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
    if (response.type && response.type === 'store.take') {
        storeTake(response);
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