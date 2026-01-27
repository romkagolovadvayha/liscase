/**
 * Обработка избранного для товаров на главной странице и в маркете
 */
$(document).ready(function() {
    // Обработчик клика по звездочке избранного
    $(document).on('click', '.category-card__favorite, .set__favorite', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $button = $(this);
        var productId = $button.data('product-id') || $button.attr('data-product-id');
        
        if (!productId) {
            console.error('Product ID not found');
            return;
        }
        
        var $svg = $button.find('svg path');
        
        $.ajax({
            url: '/market/toggle-favorite',
            type: 'POST',
            data: {id: productId},
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.isFavorite) {
                        $button.addClass('active');
                        $svg.attr('fill', '#f8b34d').attr('stroke', '#f8b34d');
                        $button.attr('title', 'Удалить из избранного');
                    } else {
                        $button.removeClass('active');
                        $svg.attr('fill', 'none').attr('stroke', '#564a66');
                        $button.attr('title', 'Добавить в избранное');
                    }
                    
                    // Показываем уведомление, если доступно
                    if (typeof toastr !== 'undefined') {
                        toastr.success('<i class=\'fas fa-check-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
                    }
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>' + response.message + '</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
                    }
                }
            },
            error: function() {
                if (typeof toastr !== 'undefined') {
                    toastr.error('<i class=\'fas fa-exclamation-circle\'></i><div class=\'toast-message_text\'>Произошла ошибка при обновлении избранного</div>', '', {'progressBar': true, 'positionClass': 'toast-top-right', 'escapeHtml': false,});
                }
            }
        });
    });
});

