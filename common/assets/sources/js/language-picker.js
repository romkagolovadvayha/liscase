/**
 * Created on : 2014.08.24., 5:26:26
 * Author     : Lajos Molnar <lajax.m@gmail.com>
 */
$(document).ready(function () {
    LanguagePicker.init();
});

var LanguagePicker = {
    init: function () {
        $('body').on('click', '.language-picker.dropdown-list > div > a', $.proxy(function (event) {
            event.preventDefault();

            var obj = $(event.currentTarget).closest('.dropdown-list').find('ul');

            if (obj.hasClass('active')) {
                obj.removeClass('active').hide();
            } else {
                obj.addClass('active').show();
            }
        }, this));

        $('body').on('click', function (e) {
            if (!$(e.target).parents().hasClass('dropdown-list')
                && $('.dropdown-list ul.active').length !== 0
            ) {
                $('.dropdown-list ul.active').removeClass('active').hide();
            }
        });

        $('.header-language-picker ul').hide();
        $('.header-language-picker').show();
    },
};
