var chat = new WebSocket('wss://ws.prostoj.store');

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
};
chat.onopen = function(e) {
    if (token !== undefined) {
        chat.send( JSON.stringify({'action' : 'auth', 'token' : token, 'steam_id' : steam_id}) );
    }
    if (chatId !== undefined) {
        chat.send(JSON.stringify({'action': 'subscription', 'chat': chatId}));
    }
};