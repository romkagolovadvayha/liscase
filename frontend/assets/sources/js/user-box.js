// $('.variable-width').slick({
//     dots: true,
//     infinite: true,
//     speed: 300,
//     slidesToShow: 1,
//     centerMode: true,
//     variableWidth: true
// });
var roulete_open_content = undefined;
var blockedRoulete = false;
var boxFree = false;
// var win_drop = undefined;
function slickRouleteInit() {
    var roulete = $('.roulete').slick({
        centerMode: true,
        centerPadding: '60px',
        speed: 9000,
        slidesToShow: 3,
        arrows: false,
        touchMove: false
    });
    $('.roulete_blur').slick({
        centerMode: true,
        centerPadding: '60px',
        speed: 9000,
        slidesToShow: 7,
        arrows: false,
        touchMove: false,
        slidesToScroll: 1
    });

    var stopAudio = new Audio("/audio/gambling.mp3");
    roulete.on('afterChange', function(event, slick, currentSlide, nextSlide){
        // $('.box_entity_card_actions_btn').removeClass('disabled');
        blockedRoulete = false;
        stopAudio.play();
        // win_drop.addClass('active');
        // setTimeout(function () {
            // win_drop.removeClass('active');
        // }, 8200);
    });
    var startAudio = new Audio("/audio/go-new-gambling.mp3");
    roulete.on('beforeChange', function(event, slick, currentSlide, nextSlide){
        console.log('go-new-gambling.mp3');
        startAudio.play();
    });

    roulete_open_content = $('.roulete_open_content');
    blockedRoulete = false;
    boxFree = false;
    // win_drop = $('#win_drop');
    $('.box_entity_card_actions_btn').on('click', function () {
        if (blockedRoulete) {
            return false;
        }
    });
    // $('.box_entity_card_actions_btn_free').on('click', function () {
    //     boxFree = true;
    //     return false;
    // });
    $('#roulete-container').on('submit', function (e) {
        e.preventDefault();
        if (blockedRoulete) {
            return false;
        }

        if (boxFree) {
            $('.box_entity_card_actions_btn').hide();
            $('.box_entity_card_actions_inventory_action').show();
        }
        $('.box_entity_card_actions_btn').addClass('disabled');
        blockedRoulete = true;
        // win_drop.removeClass('active');
        var $yiiform = $(this);
        $.ajax({
                type: $yiiform.attr('method'),
                url: $yiiform.attr('action'),
                data: $yiiform.serializeArray()
        }).done(function(data) {
            roulete_open_content.html(data);
            slickRouleteInit();
            var number = $('.roulete_wrapper').data().success;
            $('.roulete').slick('slickGoTo', number);
            $('.roulete_blur').slick('slickGoTo', number);

            var slickCurrent = $('.roulete_main_wrap .slick-active.slick-current').clone();
            slickCurrent.removeClass('roulete_item drop_card slick-slide slick-current slick-active slick-center');
            // slickCurrent.addClass('win_drop_item');
            $('.products_item_roulete').addClass('products_item_roulete_blocked');
            $('.products_item_roulete').html('<div class="products_item_roulete_blocked_title"><i class="far fa-clock"></i> Бесплатная рулетка будет доступна <span class="products_item_roulete_blocked_title_timer">через 18 часов</span></div>');

            slickCurrent.removeAttr('style');
            // win_drop.html(slickCurrent);
            updateBalance();
        }).fail(function () {});
        return false;
    });
}