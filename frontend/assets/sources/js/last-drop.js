function updateLastDrops() {
    setTimeout(() => {
        $.ajax({
            url: '/site/last-drops',
            success: function (res) {
                if (res) {
                    var userDrops = JSON.parse(res);
                    if (userDrops[0].id !== lastUserDropId) {
                        var _newData = [];
                        for (var i = 0; i < userDrops.length; i++) {
                            if (userDrops[i].id === lastUserDropId) {
                                break;
                            }
                            _newData[_newData.length] = userDrops[i];
                        }
                        for (var k = _newData.length - 1; k >= 0; k--) {
                            $('.last_drops_wrapper .hide').remove();
                            $('.last_drops_wrapper .last_drops_item:last-of-type').addClass('hide');
                            $('.last_drops_wrapper .last_drops').prepend(_newData[k].view);
                            lastUserDropId = _newData[k].id;
                        }
                    }
                }
            }
        });
        updateLastDrops();
    }, 6000);
}
updateLastDrops();