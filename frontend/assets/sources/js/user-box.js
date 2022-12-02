// $('.variable-width').slick({
//     dots: true,
//     infinite: true,
//     speed: 300,
//     slidesToShow: 1,
//     centerMode: true,
//     variableWidth: true
// });
function slickRouleteInit() {
    var roulete = $('.roulete').slick({
        centerMode: true,
        centerPadding: '60px',
        speed: 10000,
        slidesToShow: 3,
        arrows: false,
        touchMove: false,
        // responsive: [
        //     {
        //         breakpoint: 768,
        //         settings: {
        //             arrows: false,
        //             centerMode: true,
        //             centerPadding: '40px',
        //             slidesToShow: 3
        //         }
        //     },
        //     {
        //         breakpoint: 480,
        //         settings: {
        //             arrows: false,
        //             centerMode: true,
        //             centerPadding: '40px',
        //             slidesToShow: 1
        //         }
        //     }
        // ]
    });
    $('.roulete_blur').slick({
        centerMode: true,
        centerPadding: '60px',
        speed: 10000,
        slidesToShow: 7,
        arrows: false,
        touchMove: false,
        slidesToScroll: 1,
        // responsive: [
        //     {
        //         breakpoint: 768,
        //         settings: {
        //             arrows: false,
        //             centerMode: true,
        //             centerPadding: '40px',
        //             slidesToShow: 3
        //         }
        //     },
        //     {
        //         breakpoint: 480,
        //         settings: {
        //             arrows: false,
        //             centerMode: true,
        //             centerPadding: '40px',
        //             slidesToShow: 1
        //         }
        //     }
        // ]
    });

    // $('#roulete_start').click(function () {
    //     roulete.slick('slickGoTo', 180);
    //     rouleteBlur.slick('slickGoTo', 180);
    // });

    roulete.on('afterChange', function(event, slick, currentSlide, nextSlide){
        $('.box_entity_card_actions_btn').removeClass('disabled');
        blockedRoulete = false;
    });
}
slickRouleteInit();
// var audio = new Audio("/audio/roll.mp3");
// roulete.on('breakpoint', function(event, slick, currentSlide, nextSlide){
//     console.log(nextSlide);
//     audio.play();
// });

// var roulete_open = $('#roulete_open');
var roulete_open_content = $('.roulete_open_content');
var blockedRoulete = false;
var openBoxModal = new bootstrap.Modal(document.getElementById('openBoxModal'));
var notBalanceModal = new bootstrap.Modal(document.getElementById('notBalanceModal'));
$('.box_entity_card_actions_btn').on('click', function () {
    if (blockedRoulete) {
        return false;
    }
    if (balance >= boxPrice) {
        openBoxModal.show();
    } else {
        notBalanceModal.show();
    }
    return false;
});
$('#buy-free-container, #buy-container').on('beforeSubmit', function () {
    if (blockedRoulete) {
        return false;
    }
    $('.box_entity_card_actions_btn').addClass('disabled');
    blockedRoulete = true;
    var $yiiform = $(this);
    $.ajax({
            type: $yiiform.attr('method'),
            url: $yiiform.attr('action'),
            data: $yiiform.serializeArray()
        }
    ).done(function(data) {
            roulete_open_content.html(data);
            slickRouleteInit();
            var number = $('.roulete_wrapper').data().success;
            $('.roulete').slick('slickGoTo', number);
            $('.roulete_blur').slick('slickGoTo', number);
            updateBalance();
    }).fail(function () {})
    return false;
});