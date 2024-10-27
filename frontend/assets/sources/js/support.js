$('.support_messages_wrap').scrollTop($('#chat').height());
function supportChat(response) {
    $.ajax({
        url: '/support/get-message?id=' + response.messageId,
        success: function (res) {
            if (res) {
                $('#chat').append(res);
                $('.support_messages_wrap').scrollTop($('#chat').height());
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
$(document).keydown(function(e) {
    if(e.keyCode === 13) {
        var el = $('#supportMessage');
        if (el.val()) {
            chat.send( JSON.stringify({'action' : 'chat', 'message' : el.val(), 'chatId': chatId}) );
            el.val('');
        }
    }
});
async function sendFile() {
    var file = document.getElementById('supportMessageFile').files[0];
    let blob = new Blob([file, {type: file.type}]);
    var reader = new FileReader();
    reader.loadend = function() {
        alert("the File has been transferred.");
    }
    reader.onload = function(e) {
        var rawData = e.target.result;
        chat.send( JSON.stringify({'action' : 'chatFile', 'data': rawData, 'filename': file.name, 'type': file.type, 'chatId': chatId}) );
    }
    reader.readAsDataURL(blob);
}
$('#supportMessageFile').on('change', function () {
    sendFile();
});
$('#supportMessage').on('focus', function () {
    chat.send( JSON.stringify({'action' : 'chatFocus', 'chatId': chatId}) );
});
$('#supportMessage').on('blur', function () {
    chat.send( JSON.stringify({'action' : 'chatBlur', 'chatId': chatId}) );
});