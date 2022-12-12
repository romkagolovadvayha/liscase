// var connBattle = undefined;
// $(function() {
//     connBattle = new WebSocket('ws://liscase.local:8080/battleonline?q=query');
//     connBattle.onmessage = function(e) {
//         var message = JSON.parse(e.data);
//         console.log(message);
//         //console.log('Response:' + e.data);
//     };
//     connBattle.sendReject = function() {
//         var data = {'action' : 'reject', 'session_id': session_id};
//         connBattle.send(JSON.stringify(data));
//     };
// })

function updateBattleList() {
    setTimeout(() => {
        $.ajax({
            url: '/battle/get-list?status=' + battleStatus,
            success: function (res) {
                if (res) {
                    $('.battle_rows_wrapper').html(res);
                }
            }
        });
        updateBattleList();
    }, 5000);
}
updateBattleList();