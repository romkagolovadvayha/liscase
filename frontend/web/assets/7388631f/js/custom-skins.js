var like_items = $('.custom-skins_content_list_item_images_like');
if (like_items) {
    like_items.on('click', function () {
        var id = $(this).attr('data-id');
        var guest = $(this).attr('data-guest');
        if (guest == 1) {
            toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Чтобы голосовать, вам нужно авторизоваться на сайте.</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
            return;
        }
        var el = $('.custom-skins_content_list_item_images_like[data-id=' + id + ']');
        var count = 0;
        if (el.hasClass('active')) {
            el.removeClass('active');
            count = parseInt(el.find('.custom-skins_content_list_item_images_like_count').html());
            el.find('.custom-skins_content_list_item_images_like_count').html(count - 1);
        } else {
            el.addClass('active');
            count = parseInt(el.find('.custom-skins_content_list_item_images_like_count').html());
            el.find('.custom-skins_content_list_item_images_like_count').html(count + 1);
        }
        $.ajax({
            url: '/custom-skins/like?id=' + id,
            type: 'POST',
            error: function (xhr, status, error) {},
            success: function (result, status, xhr) {}
        });
    });
}