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
            href
        );
        return false;
    });

});

function openModal(modalId, size, title, href) {
    var mQ = $('#' + modalId);
    if (!modalId || !mQ.length) {
        modalId = 'modal-dialog';
    }

    var modal = new bootstrap.Modal(document.getElementById(modalId), {});
    modal.show();
    mQ.removeClass('modal-lg');
    mQ.removeClass('modal-sm');

    var modalEl = document.getElementById('modal-dialog');
    var modalBody = $(modalEl).find('.modal-body');
    modalBody.load(href, function () {
        if (title) {
            $(modalEl).find('.modal-header .modal-title').html(title);
        }
        if (size) {
            $(modalEl).addClass(size);
        }
        if ($('.roulete').length && $('.roulete_blur').length) {
            slickRouleteInit();
        }
        // setTimeout(function () {
        //
        // }, 500);
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