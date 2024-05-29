function loopUpdateBalance() {
    setTimeout(() => {
        updateBalance();
        loopUpdateBalance();
    }, 10000);
}
loopUpdateBalance();

function updateBalance() {
    $.ajax({
        url: '/user/get-balance',
        success: function (res) {
            if (res) {
                var data = JSON.parse(res);
                balanceStr = data.balanceStr;
                balance = data.balance;
                $('.balance_count').html(balanceStr);
            }
        }
    });
}

if ($('#achievements_achievement_btn').length) {
    $('#achievements_achievement_btn').click(function (e) {
        e.preventDefault();
        $(this).addClass('active');
        $('#achievements_daily_btn').removeClass('active');
        $("#achievements_daily_body").removeClass('active');
        $("#achievements_achievement_body").addClass('active');
    });
}
if ($('#achievements_daily_btn').length) {
    $('#achievements_daily_btn').click(function (e) {
        e.preventDefault();
        $(this).addClass('active');
        $('#achievements_achievement_btn').removeClass('active');
        $("#achievements_achievement_body").removeClass('active');
        $("#achievements_daily_body").addClass('active');
    });
}