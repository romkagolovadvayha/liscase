$('#toggle_header_menu').on('click', function () {
    $('#header_menu').toggle();
    $('body').toggleClass('shadow');
    return false;
});