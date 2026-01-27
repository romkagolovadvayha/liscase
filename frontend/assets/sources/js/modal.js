$(document).ready(function () {
    $(document).on('click', '.show-modal-link', function (e) {
        e.preventDefault();
        $('.modal-backdrop').remove();
        var href = $(this).data('href');
        if (!href) {
            href = $(this).attr('href');
        }
        openModal(
            $(this).data('target'),
            $(this).data('size'),
            $(this).data('title'),
            href,
            $(this).data('top-image'),
            $(this).data('content-overflow'),
            $(this).data('top-class')
        );
        return false;
    });

});

function openModal(modalId, size, title, href, topImage, contentOverflow, topClass) {
    var mQ = $('#' + modalId);
    if (!modalId || !mQ.length) {
        modalId = 'modal-dialog';
    }

    $('#loader').addClass('active');
    var modal = new bootstrap.Modal(document.getElementById(modalId), {});
    mQ.removeClass('modal-lg');
    mQ.removeClass('modal-sm');
    mQ.removeClass('modal-xl');
    mQ.removeClass('modal-xxl');
    var modalEl = document.getElementById('modal-dialog');
    $(modalEl).find('.modal-backdrop-image').removeClass('active');
    $(modalEl).find('.modal-content').css('overflow', 'hidden');
    $(modalEl).removeClass('with-image');
    var modalBody = $(modalEl).find('.modal-body-js');
    if (title) {
        $(modalEl).find('.modal-title-js').html(title);
    }
    if (size) {
        $(modalEl).addClass(size);
    }
    if (topImage) {
        $(modalEl).addClass('with-image');
        $(modalEl).find('.modal-backdrop-image').addClass('active');
        $(modalEl).find('.modal-backdrop-image').attr('src', topImage);
    }
    if (topClass) {
        $(modalEl).addClass('with-image');
        $(modalEl).find('.modal-backdrop-image').attr('class', 'modal-backdrop-image ' + topClass);
    }
    if (contentOverflow) {
        $(modalEl).find('.modal-content').css('overflow', contentOverflow);
    }

    modalBody.load(href, function () {
        $('#loader').removeClass('active');
        modal.show();
        modalEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((tooltip) => {
            new bootstrap.Tooltip(tooltip);
        });
        let pay_buttons = $('.pay__button');
        let paymentform__amount = $('#paymentform-amount');
        if (pay_buttons && paymentform__amount) {
            pay_buttons.click(function () {
                pay_buttons.removeClass('pay__button_active');
                $(this).addClass('pay__button_active');
                $('#paymentform-amount').val($(this).attr('data-value'));
            });
            paymentform__amount.on('input', function () {
                pay_buttons.removeClass('pay__button_active');
                let amount = $(this).val();
                for (let i = pay_buttons.length - 1; i >= 0; i--) {
                    if (amount >= parseInt($(pay_buttons[i]).attr('data-value'))) {
                        $(pay_buttons[i]).addClass('pay__button_active');
                        break;
                    }
                }
            });
        }
        
        // Триггерим кастомное событие после загрузки контента в модалку
        $(modalEl).trigger('modal.content.loaded');
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        // myInput.focus()
    });


    // let modalBody = modal.find('.modal-body');

    // setTimeout(function () {
    //     modalBody.load(href, function () {
    //         if (title) {
    //             title = '<h3>' + (title ? title : "&nbsp;") + '</h3>';
    //             modal.find('.modal-header .modal-header-title').html(title);
    //         }
    //
    //         if (size) {
    //             modal.find('.modal-dialog').addClass(size);
    //         }
    //     });
    // }, 500);
}

// Мобильное меню
$(document).ready(function () {
    const mobileMenuModal = document.getElementById('mobile-menu-modal');
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenuClose = document.querySelector('.mobile-menu-modal__close');
    const mobileMenuOverlay = document.querySelector('.mobile-menu-modal__overlay');
    
    if (mobileMenuModal && mobileMenuToggle) {
        // Открытие меню
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            mobileMenuModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
        
        // Закрытие меню
        function closeMobileMenu() {
            mobileMenuModal.style.display = 'none';
            document.body.style.overflow = '';
        }
        
        if (mobileMenuClose) {
            mobileMenuClose.addEventListener('click', closeMobileMenu);
        }
        
        if (mobileMenuOverlay) {
            mobileMenuOverlay.addEventListener('click', closeMobileMenu);
        }
        
        // Закрытие по ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileMenuModal.style.display === 'flex') {
                closeMobileMenu();
            }
        });
        
        // Закрытие при клике на ссылку в меню (кроме dropdown)
        mobileMenuModal.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && !link.hasAttribute('data-bs-toggle') && link.getAttribute('href') !== '#') {
                closeMobileMenu();
            }
        });
        
        // Инициализация Bootstrap dropdown в мобильном меню
        const dropdownToggleList = mobileMenuModal.querySelectorAll('[data-bs-toggle="dropdown"]');
        dropdownToggleList.forEach(function(dropdownToggleEl) {
            new bootstrap.Dropdown(dropdownToggleEl);
        });
    }
});