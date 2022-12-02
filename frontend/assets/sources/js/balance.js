function loopUpdateBalance() {
    setTimeout(() => {
        updateBalance();
        loopUpdateBalance();
    }, 5000);
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