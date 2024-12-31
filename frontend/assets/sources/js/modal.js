$(document).ready(function () {
    $(document).on('click', '.show-modal-link', function (e) {
        e.preventDefault();
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