/**
 * Created on : 2014.08.24., 5:26:26
 * Author     : Lajos Molnar <lajax.m@gmail.com>
 */
$(document).ready(function () {
    LanguagePicker.init();
});

var LanguagePicker = {
    init: function () {
        $('body').on('click', '.language-picker.dropdown-picker-list ul a', $.proxy(function (event) {
            this.change($(event.currentTarget).attr('href'));
            event.preventDefault();
        }, this));

        $('body').on('click', '.language-picker.dropdown-picker-list a', $.proxy(function (event) {
            event.preventDefault();

            var obj = $(event.currentTarget).closest('.dropdown-picker-list').find('ul');

            if (obj.hasClass('active')) {
                obj.removeClass('active').hide();
            } else {
                obj.addClass('active').show();
            }
        }, this));

        $('body').on('click', function (e) {
            if (!$(e.target).parents().hasClass('dropdown-picker-list')
                && $('.dropdown-picker-list ul.active').length !== 0
            ) {
                $('.dropdown-picker-list ul.active').removeClass('active').hide();
            }
        });

        $('.header-language-picker ul').hide();
        $('.header-language-picker').show();
    },
    change: function (url) {
        $.get(url, {}, function () {
            document.location.reload();
        });
    },
};
