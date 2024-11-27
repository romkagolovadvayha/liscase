function updateBalance(response) {
    balanceStr = response.balanceStr;
    balance = response.balance;
    $('.balance_count').html(balanceStr);
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