function updateOnlineCounter() {
    setTimeout(() => {
        $.ajax({
            url: '/site/online-counter',
            success: function (res) {
                if (res) {
                    $('.online_counter').html(res);
                }
            }
        });
        updateOnlineCounter();
    }, 20000);
}
updateOnlineCounter();