function supportChat(response) {
    $('#chat').append('<div class="support_messages_item">\n' +
        '                            <div class="support_messages_item_profile">\n' +
        '                                <div class="support_messages_item_profile_avatar"><img src="' + response.avatar + '"></div>\n' +
        '                            </div>\n' +
        '                            <div class="support_messages_item_message">' +
                                        '<div class="support_messages_item_message_username ' + response.usernameClass + '">' + response.username + '</div>' +
                                        '<div class="support_messages_item_message_content">' + response.message + '</div>' +
                                    '</div>\n' +
        '                        </div>');
}
function supportChatFocus(response) {
    $('#supportChatWrited').addClass('active');
    $('#supportChatWrited').html(response.content);
}
function supportChatBlur(response) {
    $('#supportChatWrited').removeClass('active');
}
$('#btnSend').click(function() {
    if ($('#message').val()) {
        chat.send( JSON.stringify({'action' : 'chat', 'message' : $('#message').val()}) );
    } else {
        alert('Enter the message')
    }
});
$('#message').on('focus', function () {
    chat.send( JSON.stringify({'action' : 'chatFocus'}) );
});
$('#message').on('blur', function () {
    chat.send( JSON.stringify({'action' : 'chatBlur'}) );
});