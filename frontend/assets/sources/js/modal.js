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
    modalId = String(modalId || 'modal-dialog').replace(/^#/, '');
    var modalEl = document.getElementById(modalId);
    if (!modalEl) {
        modalId = 'modal-dialog';
        modalEl = document.getElementById(modalId);
    }
    if (!modalEl) return;

    $('#loader').addClass('active');
    var mQ = $(modalEl);
    var modal = typeof bootstrap.Modal.getOrCreateInstance === 'function'
        ? bootstrap.Modal.getOrCreateInstance(modalEl, {})
        : new bootstrap.Modal(modalEl, {});
    var modalDialog = mQ.find('.modal-dialog').first();
    modalDialog.removeClass('modal-lg modal-sm modal-xl modal-xxl');
    mQ.find('.modal-backdrop-image').removeClass('active');
    mQ.find('.modal-content').css('overflow', 'hidden');
    mQ.removeClass('with-image');
    var modalBody = mQ.find('.modal-body-js').first();
    if (title) {
        mQ.find('.modal-title-js').text(title);
    }
    if (size) {
        modalDialog.addClass(size);
    }
    if (topImage) {
        mQ.addClass('with-image');
        mQ.find('.modal-backdrop-image').addClass('active').attr('src', topImage);
    }
    if (topClass) {
        mQ.addClass('with-image');
        mQ.find('.modal-backdrop-image').attr('class', 'modal-backdrop-image ' + topClass);
    }
    if (contentOverflow) {
        mQ.find('.modal-content').css('overflow', contentOverflow);
    }

    modalEl.setAttribute('aria-busy', 'true');
    if (!modalBody.length || !href) {
        $('#loader').removeClass('active');
        modalEl.removeAttribute('aria-busy');
        modal.show();
        return;
    }
    modalBody.load(href, function () {
        $('#loader').removeClass('active');
        modalEl.removeAttribute('aria-busy');
        modal.show();
        modalEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((tooltip) => {
            if (typeof bootstrap.Tooltip.getOrCreateInstance === 'function') {
                bootstrap.Tooltip.getOrCreateInstance(tooltip);
            } else {
                new bootstrap.Tooltip(tooltip);
            }
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
